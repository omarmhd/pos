<?php

namespace App\Http\Controllers;

use App\Models\CustomsDeclaration;
use App\Models\Setting;
use App\Models\Supplier;
use Illuminate\Http\Request;

/**
 * الإقرارات الجمركية (Customs Declarations / Import VAT).
 * تسجّل قيمة الواردات والرسوم الجمركية وضريبة القيمة المضافة على الواردات (مدخلات)،
 * وتُدرَج لاحقاً في كشف الإيرادات والمصروفات بعضوية حصرية.
 */
class CustomsDeclarationController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:customs.view')->only(['index', 'show']);
        $this->middleware('can:customs.manage')->only(['create', 'store', 'destroy']);
    }

    public function index()
    {
        $declarations = CustomsDeclaration::with('supplier', 'user', 'resStatement')
            ->orderByDesc('declaration_date')->orderByDesc('id')
            ->get();
        $currency = Setting::get('currency_symbol', 'ج.م');

        return view('customs.index', compact('declarations', 'currency'));
    }

    public function create()
    {
        $suppliers = Supplier::orderBy('name')->get(['id', 'name']);
        $currency  = Setting::get('currency_symbol', 'ج.م');

        return view('customs.create', compact('suppliers', 'currency'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'declaration_date' => 'required|date',
            'supplier_id'      => 'nullable|exists:suppliers,id',
            'vendor_name'      => 'nullable|string|max:255',
            'customs_ref'      => 'nullable|string|max:255',
            'total_amount'     => 'required|numeric|min:0',
            'tax_amount'       => 'required|numeric|min:0',
            'notes'            => 'nullable|string|max:500',
        ]);
        $data['user_id'] = auth()->id();

        $declaration = CustomsDeclaration::create($data);

        return redirect()->route('customs-declarations.show', $declaration)
            ->with('success', 'تم تسجيل الإقرار الجمركي — ' . $declaration->fresh()->declaration_number);
    }

    public function show(CustomsDeclaration $customs_declaration)
    {
        $customs_declaration->load('supplier', 'user', 'resStatement');
        $currency = Setting::get('currency_symbol', 'ج.م');

        return view('customs.show', compact('customs_declaration', 'currency'));
    }

    public function destroy(CustomsDeclaration $customs_declaration)
    {
        if ($customs_declaration->res_statement_id) {
            return back()->with('error', 'لا يمكن حذف إقرار مُدرَج ضمن كشف. أزله من الكشف أولاً.');
        }

        $customs_declaration->delete();

        return redirect()->route('customs-declarations.index')
            ->with('success', 'تم حذف الإقرار الجمركي');
    }
}
