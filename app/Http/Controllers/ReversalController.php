<?php

namespace App\Http\Controllers;

use App\Models\Reversal;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Services\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReversalController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Reversal::class);
        $branchId = $this->effectiveBranchId($request);

        // Filter reversals through their reversal_journal_entry branch_id
        $reversals = Reversal::with('createdBy')
            ->when($branchId, function ($q) use ($branchId) {
                $q->whereHas('journalEntry', fn($je) => $je->where('branch_id', $branchId));
            })
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
        $original_id = $request->input('original_id');

        return view('reversals.create', compact('original_type', 'original_id'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Reversal::class);
        $data = $request->validate([
            'original_type' => 'required|string',
            'original_id' => 'required|integer',
            'reason' => 'nullable|string',
        ]);

        $originalType = $data['original_type'];
        $originalId = $data['original_id'];

        // allow only Sale or Purchase for now
        if (!in_array($originalType, [Sale::class, Purchase::class])) {
            return back()->withErrors(['original_type' => 'Invalid original type']);
        }

        $original = ($originalType)::find($originalId);
        if (!$original) return back()->withErrors(['original_id' => 'Original record not found']);

        if (!$original->is_posted) {
            return back()->withErrors(['original_id' => 'Original record is not posted and cannot be reversed']);
        }

        // check existing reversal or already reversed flag
        if (Reversal::where('original_type', $originalType)->where('original_id', $originalId)->exists() || ($original->is_reversed ?? false)) {
            return back()->withErrors(['original_id' => 'A reversal already exists for this record']);
        }

        DB::transaction(function () use ($original, $originalType, $originalId, $data) {
            // Find original GL entry
            $je = JournalEntry::where('source_type', $originalType)->where('source_id', $originalId)->first();
            if (!$je) throw new \Exception('Original journal entry not found');

            // ACC-3: Reversal GL entry inherits branch_id from the original entry
            $revEntry = JournalEntry::create([
                'entry_date'  => now(),
                'reference'   => 'REV-' . ($original->invoice_number ?? $originalId),
                'source_type' => $originalType,
                'source_id'   => $originalId,
                'branch_id'   => $je->branch_id,
                'description' => 'قيد عكسي للقيد رقم ' . $je->entry_number . ' — ' . class_basename($originalType) . ' #' . $originalId,
                'user_id'     => auth()->id(),
                'posted_at'   => now(),
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

            // ACC-4: Stock reversal via WarehouseService (updates stock_levels + products.quantity).
            // warehouse_id is taken from the original transaction (sale/purchase warehouse).
            // This was previously: product->increment/decrement() which bypassed stock_levels entirely
            // and also produced StockMovement rows without warehouse_id (NOT NULL constraint → DB crash).
            if ($originalType === Sale::class) {
                $warehouseId = $original->warehouse_id
                    ?? \App\Services\WarehouseService::getDefault()->id;

                foreach ($original->items as $item) {
                    WarehouseService::in($warehouseId, $item->product_id, (float) $item->quantity);

                    StockMovement::create([
                        'product_id'     => $item->product_id,
                        'warehouse_id'   => $warehouseId,
                        'reference_type' => Reversal::class,
                        'reference_id'   => $revEntry->id,
                        'quantity'       => $item->quantity,
                        'cost'           => $item->cost_price ?? 0,
                        'movement_type'  => 'in',
                        'notes'          => 'عكس مبيعات #' . ($original->invoice_number ?? $originalId),
                    ]);
                }
            }

            if ($originalType === Purchase::class) {
                $warehouseId = $original->warehouse_id
                    ?? \App\Services\WarehouseService::getDefault()->id;

                foreach ($original->items as $item) {
                    WarehouseService::out($warehouseId, $item->product_id, (float) $item->quantity);

                    StockMovement::create([
                        'product_id'     => $item->product_id,
                        'warehouse_id'   => $warehouseId,
                        'reference_type' => Reversal::class,
                        'reference_id'   => $revEntry->id,
                        'quantity'       => $item->quantity,
                        'cost'           => $item->unit_price ?? 0,
                        'movement_type'  => 'out',
                        'notes'          => 'عكس مشتريات #' . ($original->invoice_number ?? $originalId),
                    ]);
                }
            }

            $reversal = Reversal::create([
                'original_type' => $originalType,
                'original_id' => $originalId,
                'reversal_journal_entry_id' => $revEntry->id,
                'reason' => $data['reason'] ?? null,
                'created_by' => auth()->id(),
            ]);

            // mark original as reversed to prevent double reversal
            $original->is_reversed = true;
            $original->save();

            \App\Models\AuditLog::create([
                'user_id'        => auth()->id(),
                'auditable_type' => Reversal::class,
                'auditable_id'   => $reversal->id,
                'action'         => 'created',
                'ip_address'     => request()->ip() ?? null,
            ]);
        });

        return redirect()->route('reversals.index')->with('success', 'تم إنشاء القيد العكسي بنجاح');
    }
}
