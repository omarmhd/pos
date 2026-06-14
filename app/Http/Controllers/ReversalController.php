<?php

namespace App\Http\Controllers;

use App\Models\InventoryAdjustment;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\PaymentVoucher;
use App\Models\PayrollRun;
use App\Models\Purchase;
use App\Models\ReceiptVoucher;
use App\Models\Reversal;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Services\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReversalController extends Controller
{
    /** All source types that can be reversed. */
    private const ALLOWED_TYPES = [
        Sale::class,
        Purchase::class,
        ReceiptVoucher::class,
        PaymentVoucher::class,
        PayrollRun::class,
        InventoryAdjustment::class,
    ];

    /** Human-readable labels for the UI. */
    private const TYPE_LABELS = [
        Sale::class                => 'فاتورة مبيعات',
        Purchase::class            => 'فاتورة مشتريات',
        ReceiptVoucher::class      => 'سند قبض',
        PaymentVoucher::class      => 'سند صرف',
        PayrollRun::class          => 'مسير رواتب',
        InventoryAdjustment::class => 'تعديل مخزون',
    ];

    public function index(Request $request)
    {
        $this->authorize('viewAny', Reversal::class);
        $branchId = $this->effectiveBranchId($request);

        $reversals = Reversal::with('createdBy')
            ->when($branchId, fn($q) =>
                $q->whereHas('journalEntry', fn($je) => $je->where('branch_id', $branchId)))
            ->orderBy('created_at', 'desc')
            ->paginate(30);

        $branches     = \App\Models\Branch::where('is_active', true)->orderBy('name')->get(['id','name','code']);
        $branchLocked = $this->isBranchLocked();
        return view('reversals.index', compact('reversals', 'branches', 'branchId', 'branchLocked'));
    }

    public function show($id)
    {
        $reversal = Reversal::findOrFail($id);
        $this->authorize('view', $reversal);
        $reversal->load('createdBy', 'journalEntry.lines.account');
        return view('reversals.show', compact('reversal'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Reversal::class);
        $original_type = $request->input('original_type');
        $original_id   = $request->input('original_id');
        $typeLabels    = self::TYPE_LABELS;
        return view('reversals.create', compact('original_type', 'original_id', 'typeLabels'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Reversal::class);

        $data = $request->validate([
            'original_type' => 'required|string',
            'original_id'   => 'required|integer',
            'reason'        => 'nullable|string|max:500',
        ]);

        $originalType = $data['original_type'];
        $originalId   = (int) $data['original_id'];

        // ── Guard: only allow known types ────────────────────────────────────
        if (!in_array($originalType, self::ALLOWED_TYPES, true)) {
            return back()->withErrors(['original_type' =>
                'نوع السجل غير مدعوم للعكس. الأنواع المدعومة: '
                . implode('، ', array_map('class_basename', self::ALLOWED_TYPES))
            ]);
        }

        $original = $originalType::find($originalId);
        if (!$original) {
            return back()->withErrors(['original_id' => 'السجل الأصلي غير موجود']);
        }

        // ── Guard: must be in a "posted/approved" state ──────────────────────
        if (!$this->isPosted($original, $originalType)) {
            return back()->withErrors(['original_id' =>
                'لا يمكن عكس سجل غير مُرحَّل أو غير معتمد'
            ]);
        }

        // ── Guard: no double reversal ────────────────────────────────────────
        $alreadyReversed = Reversal::where('original_type', $originalType)
                                   ->where('original_id', $originalId)
                                   ->exists()
                        || ($original->is_reversed ?? false);
        if ($alreadyReversed) {
            return back()->withErrors(['original_id' => 'يوجد قيد عكسي لهذا السجل مسبقاً']);
        }

        DB::transaction(function () use ($original, $originalType, $originalId, $data) {
            \App\Services\PeriodLockService::assertOpen(now()->toDateString());

            // ── Find the original GL entry ────────────────────────────────────
            $je = JournalEntry::where('source_type', $originalType)
                              ->where('source_id', $originalId)
                              ->first();
            if (!$je) {
                throw new \RuntimeException('لم يُعثر على القيد المحاسبي الأصلي');
            }

            // ── Build reversal GL entry (swap debit ↔ credit) ─────────────────
            $ref = $this->refLabel($original, $originalId);

            $revEntry = JournalEntry::create([
                'entry_date'  => now(),
                'reference'   => 'REV-' . $ref,
                'source_type' => $originalType,
                'source_id'   => $originalId,
                'branch_id'   => $je->branch_id,
                'user_id'     => auth()->id(),
                'posted_at'   => now(),
                'description' => 'قيد عكسي — ' . (self::TYPE_LABELS[$originalType] ?? class_basename($originalType))
                                 . ' #' . $ref . ' / قيد رقم ' . $je->entry_number,
            ]);

            foreach ($je->lines as $line) {
                JournalEntryLine::create([
                    'journal_entry_id' => $revEntry->id,
                    'account_id'       => $line->account_id,
                    'debit'            => $line->credit,
                    'credit'           => $line->debit,
                    'line_description' => 'عكس — ' . ($line->line_description ?? ''),
                ]);
            }

            // ── Per-type stock reversal ───────────────────────────────────────
            $this->reverseStock($original, $originalType, $revEntry->id);

            // ── Persist Reversal record ───────────────────────────────────────
            $reversal = Reversal::create([
                'original_type'             => $originalType,
                'original_id'               => $originalId,
                'reversal_journal_entry_id' => $revEntry->id,
                'reason'                    => $data['reason'] ?? null,
                'created_by'                => auth()->id(),
            ]);

            // ── Mark original to prevent double reversal ─────────────────────
            $this->markReversed($original, $originalType);

            \App\Models\AuditLog::create([
                'user_id'        => auth()->id(),
                'auditable_type' => Reversal::class,
                'auditable_id'   => $reversal->id,
                'action'         => 'created',
                'ip_address'     => request()->ip(),
            ]);
        });

        return redirect()->route('reversals.index')
            ->with('success', 'تم إنشاء القيد العكسي بنجاح');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /** Check if the original record is in a reversible state */
    private function isPosted(mixed $original, string $type): bool
    {
        return match($type) {
            PayrollRun::class          => $original->status === 'approved',
            InventoryAdjustment::class => true,  // always effective once created
            default                    => (bool) ($original->is_posted ?? false),
        };
    }

    /** Get a short reference label from the original record */
    private function refLabel(mixed $original, int $fallback): string
    {
        return $original->invoice_number
            ?? $original->voucher_number
            ?? $original->reference
            ?? $original->periodLabel()
            ?? (string) $fallback;
    }

    /**
     * Apply the inventory reversal for stock-affecting source types.
     * GL-only types (vouchers, payroll) need no stock action.
     */
    private function reverseStock(mixed $original, string $type, int $revEntryId): void
    {
        $warehouseId = ($original->warehouse_id ?? null)
                    ?? WarehouseService::getDefault()->id;

        if ($type === Sale::class) {
            foreach ($original->items as $item) {
                WarehouseService::in($warehouseId, $item->product_id, (float) $item->quantity);
                StockMovement::create([
                    'product_id'     => $item->product_id,
                    'warehouse_id'   => $warehouseId,
                    'reference_type' => Reversal::class,
                    'reference_id'   => $revEntryId,
                    'quantity'       => $item->quantity,
                    'cost'           => $item->cost_price ?? 0,
                    'movement_type'  => 'in',
                    'notes'          => 'عكس مبيعات',
                ]);
            }
            return;
        }

        if ($type === Purchase::class) {
            foreach ($original->items as $item) {
                WarehouseService::out($warehouseId, $item->product_id, (float) $item->quantity);
                StockMovement::create([
                    'product_id'     => $item->product_id,
                    'warehouse_id'   => $warehouseId,
                    'reference_type' => Reversal::class,
                    'reference_id'   => $revEntryId,
                    'quantity'       => $item->quantity,
                    'cost'           => $item->unit_price ?? 0,
                    'movement_type'  => 'out',
                    'notes'          => 'عكس مشتريات',
                ]);
            }
            return;
        }

        if ($type === InventoryAdjustment::class) {
            $qty    = abs((float) $original->quantity_change);
            $isPlus = (float) $original->quantity_change > 0;

            if ($isPlus) {
                // Original added stock → reversal removes it
                WarehouseService::out($warehouseId, $original->product_id, $qty);
                $direction = 'out';
            } else {
                // Original removed stock → reversal adds it back
                WarehouseService::in($warehouseId, $original->product_id, $qty);
                $direction = 'in';
            }

            StockMovement::create([
                'product_id'     => $original->product_id,
                'warehouse_id'   => $warehouseId,
                'reference_type' => Reversal::class,
                'reference_id'   => $revEntryId,
                'quantity'       => $qty,
                'cost'           => $original->cost_per_unit ?? 0,
                'movement_type'  => $direction,
                'notes'          => 'عكس تعديل مخزون #' . $original->id,
            ]);
            return;
        }

        // ReceiptVoucher, PaymentVoucher, PayrollRun → GL reversal only, no stock impact
    }

    /** Mark the original record as reversed (per-model strategy) */
    private function markReversed(mixed $original, string $type): void
    {
        match($type) {
            PayrollRun::class => $original->update(['status' => 'reversed']),
            // Sale + Purchase have is_reversed column
            Sale::class, Purchase::class => (function () use ($original) {
                $original->is_reversed = true;
                $original->save();
            })(),
            // Vouchers + InventoryAdjustment: rely on Reversal table existence check
            default => null,
        };
    }
}
