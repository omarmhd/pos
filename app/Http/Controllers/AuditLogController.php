<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class AuditLogController extends Controller
{
    /** Arabic labels for tracked model names */
    private const MODEL_LABELS = [
        'Sale'                => 'فاتورة بيع',
        'Purchase'            => 'فاتورة شراء',
        'JournalEntry'        => 'قيد يومي',
        'JournalEntryLine'    => 'سطر قيد',
        'CustomerPayment'     => 'دفعة عميل',
        'SupplierPayment'     => 'دفعة مورد',
        'ReceiptVoucher'      => 'سند قبض',
        'PaymentVoucher'      => 'سند صرف',
        'Customer'            => 'عميل',
        'Supplier'            => 'مورد',
        'InventoryAdjustment' => 'تعديل مخزون',
        'FixedAsset'          => 'أصل ثابت',
        'Account'             => 'حساب',
        'Reversal'            => 'قيد عكسي',
        'CashShift'           => 'وردية نقدية',
    ];

    /** Arabic labels for common field names */
    private const FIELD_LABELS = [
        'total_amount'    => 'الإجمالي',
        'paid_amount'     => 'المدفوع',
        'payment_status'  => 'حالة الدفع',
        'payment_method'  => 'طريقة الدفع',
        'is_posted'       => 'مُرحَّل',
        'is_reversed'     => 'معكوس',
        'is_credit'       => 'آجل',
        'status'          => 'الحالة',
        'credit_limit'    => 'حد الائتمان',
        'is_active'       => 'نشط',
        'name'            => 'الاسم',
        'phone'           => 'الهاتف',
        'email'           => 'البريد',
        'debit'           => 'مدين',
        'credit'          => 'دائن',
        'discount'        => 'خصم',
        'net_book_value'  => 'القيمة الدفترية',
        'accumulated_depreciation' => 'الاستهلاك المتراكم',
        'closing_amount'  => 'جرد الإقفال',
        'variance_amount' => 'الفرق النقدي',
    ];

    public function __construct()
    {
        $this->middleware('can:audit_logs.view');
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = AuditLog::with('user:id,name')
                ->select('audit_logs.*')
                ->when($request->filled('model'), fn($q) =>
                    $q->where('auditable_type', 'like', '%' . $request->model . '%'))
                ->when($request->filled('action'), fn($q) =>
                    $q->where('action', $request->action))
                ->when($request->filled('from'), fn($q) =>
                    $q->whereDate('created_at', '>=', $request->from))
                ->when($request->filled('to'), fn($q) =>
                    $q->whereDate('created_at', '<=', $request->to));

            return DataTables::eloquent($query)
                ->addColumn('time',          fn($l) => $l->created_at->format('Y-m-d H:i:s'))
                ->addColumn('user_name',     fn($l) => e($l->user?->name ?? '—'))
                ->addColumn('action_badge',  fn($l) => $this->actionBadge($l->action))
                ->addColumn('model_label',   fn($l) => $this->modelLabel($l->auditable_type))
                ->addColumn('record_link',   fn($l) => $this->recordLink($l))
                ->addColumn('diff_html',     fn($l) => $this->diffHtml($l))
                ->filterColumn('user_name',  fn($q, $k) =>
                    $q->whereHas('user', fn($u) => $u->where('name', 'like', "%$k%")))
                ->filterColumn('model_label', fn($q, $k) =>
                    $q->where('auditable_type', 'like', "%$k%"))
                ->rawColumns(['action_badge', 'model_label', 'record_link', 'diff_html'])
                ->make(true);
        }

        $modelOptions = array_keys(self::MODEL_LABELS);
        return view('audit_logs.index', compact('modelOptions'));
    }

    // ── private helpers ──────────────────────────────────────────────────────

    private function actionBadge(string $action): string
    {
        $map = [
            'created' => ['success',  'إنشاء',  'bi-plus-circle'],
            'updated' => ['primary',  'تعديل',  'bi-pencil'],
            'deleted' => ['danger',   'حذف',    'bi-trash'],
        ];
        [$color, $label, $icon] = $map[$action] ?? ['secondary', $action, 'bi-circle'];
        return "<span class='badge bg-{$color}'><i class='bi {$icon} me-1'></i>{$label}</span>";
    }

    private function modelLabel(string $class): string
    {
        $base  = class_basename($class);
        $label = self::MODEL_LABELS[$base] ?? $base;
        return "<span class='badge bg-light text-dark border small'>{$label}</span>";
    }

    private function recordLink(AuditLog $log): string
    {
        $id = $log->auditable_id;
        return "<code class='text-muted'>#{$id}</code>";
    }

    /** Build a compact diff cell — shows changed fields only for updates */
    private function diffHtml(AuditLog $log): string
    {
        if ($log->action === 'created') {
            return "<span class='text-muted small'><i class='bi bi-plus-circle text-success me-1'></i>سجل جديد</span>";
        }

        if ($log->action === 'deleted') {
            return "<span class='text-muted small'><i class='bi bi-trash text-danger me-1'></i>تم الحذف</span>";
        }

        // updated — show field-by-field diff
        $old = (array) ($log->old_values ?? []);
        $new = (array) ($log->new_values ?? []);

        if (empty($old) && empty($new)) {
            return '<span class="text-muted">—</span>';
        }

        $rows = '';
        foreach ($new as $field => $newVal) {
            $oldVal  = $old[$field] ?? null;
            $label   = self::FIELD_LABELS[$field] ?? $field;
            $oldFmt  = $this->fmtVal($oldVal);
            $newFmt  = $this->fmtVal($newVal);
            $rows   .= "<tr>
                <td class='text-muted small pe-2'>{$label}</td>
                <td class='text-danger small pe-2' style='text-decoration:line-through'>{$oldFmt}</td>
                <td class='small pe-1'>→</td>
                <td class='text-success small fw-semibold'>{$newFmt}</td>
            </tr>";
        }

        if (!$rows) {
            return '<span class="text-muted">—</span>';
        }

        return "<table class='table table-borderless table-sm mb-0' style='font-size:.72rem'>{$rows}</table>";
    }

    private function fmtVal(mixed $val): string
    {
        if (is_null($val))   return '<span class="text-muted fst-italic">فارغ</span>';
        if (is_bool($val))   return $val ? '<span class="text-success">✓</span>' : '<span class="text-danger">✗</span>';
        if (is_array($val))  return e(json_encode($val, JSON_UNESCAPED_UNICODE));
        return e((string) $val);
    }
}
