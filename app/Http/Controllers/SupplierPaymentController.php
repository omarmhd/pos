<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\LedgerPostingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierPaymentController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:suppliers.payments');
    }

    public function create(Supplier $supplier, Request $request)
    {
        $purchaseId = $request->input('purchase_id');

        // Only show unpaid/partially-paid purchases for this supplier
        $unpaidPurchases = Purchase::where('supplier_id', $supplier->id)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->latest()
            ->get()
            ->map(function ($p) {
                $p->remaining = $p->remainingAmount();
                return $p;
            })
            ->filter(fn($p) => $p->remaining > 0.005)
            ->values();

        $selectedPurchase = $purchaseId
            ? $unpaidPurchases->firstWhere('id', (int) $purchaseId)
            : null;

        $outstandingTotal = $unpaidPurchases->sum('remaining');

        return view('supplier_payments.create', compact(
            'supplier', 'unpaidPurchases', 'selectedPurchase', 'outstandingTotal'
        ));
    }

    public function store(Supplier $supplier, Request $request)
    {
        $data = $request->validate([
            'purchase_id'    => 'required|exists:purchases,id',
            'amount'         => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,card,mobile_wallet',
            'paid_at'        => 'required|date',
            'notes'          => 'nullable|string',
        ]);

        $purchase = Purchase::where('supplier_id', $supplier->id)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->findOrFail($data['purchase_id']);

        $remaining = $purchase->remainingAmount();

        if ($data['amount'] > $remaining + 0.01) {
            return back()->with('error',
                'المبلغ المدفوع أكبر من المتبقي (' . number_format($remaining, 2) . ')');
        }

        DB::transaction(function () use ($supplier, $purchase, $data) {
            // 1. Record the payment
            $payment = SupplierPayment::create([
                'supplier_id'    => $supplier->id,
                'purchase_id'    => $purchase->id,
                'amount'         => $data['amount'],
                'payment_method' => $data['payment_method'],
                'paid_at'        => $data['paid_at'],
                'notes'          => $data['notes'] ?? null,
                'user_id'        => auth()->id(),
            ]);

            // 2. Update purchase paid_amount and payment_status
            $newPaid = round((float) $purchase->paid_amount + (float) $data['amount'], 2);
            $total   = round((float) $purchase->total_amount, 2);

            $status = match (true) {
                $newPaid >= $total - 0.005 => 'paid',
                $newPaid > 0              => 'partial',
                default                   => 'unpaid',
            };

            $purchase->update([
                'paid_amount'    => $newPaid,
                'payment_status' => $status,
            ]);

            // 3. Post GL entry: DR AP → CR Cash/Bank
            (new LedgerPostingService())->postSupplierPayment($payment->load('supplier', 'purchase'));
        });

        return redirect()->route('suppliers.show', $supplier)
            ->with('success', 'تم تسجيل الدفعة للمورد وترحيل القيد المحاسبي بنجاح');
    }
}
