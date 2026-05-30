<?php

namespace App\Http\Controllers;

use App\Models\Reversal;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReversalController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Reversal::class);
        $reversals = Reversal::with('createdBy')->orderBy('created_at', 'desc')->paginate(30);
        return view('reversals.index', compact('reversals'));
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
            // find source journal entry
            $je = JournalEntry::where('source_type', $originalType)->where('source_id', $originalId)->first();
            if (!$je) throw new \Exception('Original journal entry not found');

            $revEntry = JournalEntry::create([
                'entry_date' => now(),
                'reference' => 'REV-' . ($original->invoice_number ?? $originalId),
                'source_type' => $originalType,
                'source_id' => $originalId,
                'description' => 'قيد عكسي للقيد رقم ' . $je->entry_number . ' — ' . class_basename($originalType) . ' #' . $originalId,
                'user_id' => auth()->id(),
                'posted_at' => now(),
            ]);

            foreach ($je->lines as $line) {
                JournalEntryLine::create([
                    'journal_entry_id' => $revEntry->id,
                    'account_id' => $line->account_id,
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                    'line_description' => 'عكس — ' . ($line->line_description ?? ''),
                ]);
            }

            // stock reversal: if Sale -> add stock in; if Purchase -> remove stock
            if ($originalType === Sale::class) {
                foreach ($original->items as $item) {
                    StockMovement::create([
                        'product_id' => $item->product_id,
                        'reference_type' => Reversal::class,
                        'reference_id' => $revEntry->id,
                        'quantity' => $item->quantity,
                        'cost' => $item->cost_price ?? 0,
                        'movement_type' => 'in',
                        'notes' => 'Reversal of sale #' . ($original->invoice_number ?? $originalId),
                    ]);

                    // increment product
                    if ($item->product) {
                        $item->product->increment('quantity', $item->quantity);
                    }
                }
            }

            if ($originalType === Purchase::class) {
                foreach ($original->items as $item) {
                    StockMovement::create([
                        'product_id' => $item->product_id,
                        'reference_type' => Reversal::class,
                        'reference_id' => $revEntry->id,
                        'quantity' => $item->quantity,
                        'cost' => $item->unit_price ?? 0,
                        'movement_type' => 'out',
                        'notes' => 'Reversal of purchase #' . ($original->invoice_number ?? $originalId),
                    ]);

                    if ($item->product) {
                        $item->product->decrement('quantity', $item->quantity);
                    }
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
