<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\InventoryAdjustment;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventoryAdjustmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:inventory.adjust');
    }

    public function index(Request $request)
    {
        $query = InventoryAdjustment::with('product:id,name', 'createdBy:id,name', 'journalEntry:id,entry_number')
            ->orderByDesc('created_at');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }
        if ($request->filled('reason')) {
            $query->where('reason', $request->reason);
        }

        $adjustments = $query->paginate(25)->withQueryString();
        $products    = Product::orderBy('name')->get(['id', 'name', 'barcode']);
        $currency    = Setting::get('currency_symbol', 'ج.م');

        return view('inventory.adjustments.index', compact('adjustments', 'products', 'currency'));
    }

    public function create()
    {
        return view('inventory.adjustments.create');
    }

    /** AJAX product search for adjustment create form */
    public function searchProducts(Request $request)
    {
        $q = $request->input('q', '');
        $products = Product::where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('barcode', $q);
            })
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name', 'barcode', 'quantity', 'cost_price', 'unit']);

        return response()->json($products);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id'      => 'required|exists:products,id',
            'quantity_after'  => 'required|numeric|min:0',
            'reason'          => 'required|in:' . implode(',', array_keys(InventoryAdjustment::$reasons)),
            'notes'           => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($data) {
            $product = Product::lockForUpdate()->findOrFail($data['product_id']);

            $qBefore = (float) $product->quantity;
            $qAfter  = (float) $data['quantity_after'];
            $change  = $qAfter - $qBefore;
            $cost    = (float) $product->cost_price;
            $total   = round(abs($change) * $cost, 2);

            $adj = InventoryAdjustment::create([
                'product_id'     => $product->id,
                'quantity_before'=> $qBefore,
                'quantity_after' => $qAfter,
                'quantity_change'=> $change,
                'cost_per_unit'  => $cost,
                'total_cost'     => $total,
                'reason'         => $data['reason'],
                'notes'          => $data['notes'] ?? null,
                'created_by'     => Auth::id(),
            ]);

            // Update product quantity
            $product->update(['quantity' => $qAfter]);

            // Create GL journal entry if there is a value difference
            if ($total > 0) {
                $entry = $this->createGLEntry($adj, $product);
                if ($entry) {
                    $adj->update(['journal_entry_id' => $entry->id]);
                }
            }
        });

        return redirect()->route('inventory.adjustments.index')
            ->with('success', 'تم تسجيل تعديل المخزون وإنشاء القيد المحاسبي');
    }

    public function show(InventoryAdjustment $adjustment)
    {
        $adjustment->load('product', 'journalEntry.lines.account', 'createdBy', 'session');
        return view('inventory.adjustments.show', compact('adjustment'));
    }

    // ── Build GL entry for a single adjustment ────────────────────────
    private function createGLEntry(InventoryAdjustment $adj, Product $product): ?JournalEntry
    {
        $invCode      = Setting::get('account_inventory_code', '1300');
        $shortageCode = Setting::get('account_inventory_shortage_code', '6510');
        $surplusCode  = Setting::get('account_inventory_surplus_code',  '4100');

        $invAccount   = Account::where('code', $invCode)->first();
        $otherAccount = $adj->quantity_change < 0
            ? Account::where('code', $shortageCode)->first()
            : Account::where('code', $surplusCode)->first();

        // Skip GL entry if required accounts not configured yet
        if (!$invAccount || !$otherAccount) {
            return null;
        }

        $description = $adj->quantity_change < 0
            ? "تعديل مخزون سالب — {$product->name} ({$adj->reasonLabel()})"
            : "تعديل مخزون موجب — {$product->name} ({$adj->reasonLabel()})";

        $entry = JournalEntry::create([
            'entry_date'  => now()->toDateString(),
            'description' => $description,
            'source_type' => InventoryAdjustment::class,
            'source_id'   => $adj->id,
            'user_id'     => Auth::id() ?? 1,
            'posted_at'   => now(),
        ]);

        $amount = $adj->total_cost;

        if ($adj->quantity_change < 0) {
            // Negative: DR Shortage Expense / CR Inventory
            JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => $otherAccount->id, 'debit' => $amount, 'credit' => 0]);
            JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => $invAccount->id,   'debit' => 0, 'credit' => $amount]);
        } else {
            // Positive: DR Inventory / CR Other Income
            JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => $invAccount->id,   'debit' => $amount, 'credit' => 0]);
            JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => $otherAccount->id, 'debit' => 0, 'credit' => $amount]);
        }

        return $entry;
    }
}
