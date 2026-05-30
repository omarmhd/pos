<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class AuditLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:audit_logs.view');
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = AuditLog::with('user')->select('audit_logs.*');

            return DataTables::eloquent($query)
                ->addColumn('time',           fn($l) => $l->created_at->format('Y-m-d H:i'))
                ->addColumn('user_name',      fn($l) => e($l->user?->name ?? '—'))
                ->addColumn('action_badge',   fn($l) => $this->actionBadge($l->action))
                ->addColumn('model_name',     fn($l) => e(class_basename($l->auditable_type)))
                ->addColumn('old_values_fmt', fn($l) => $l->old_values
                    ? '<pre class="small mb-0" style="white-space:pre-wrap;font-size:.7rem;max-width:200px">'
                      . e(json_encode($l->old_values, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))
                      . '</pre>'
                    : '<span class="text-muted">—</span>')
                ->addColumn('new_values_fmt', fn($l) => $l->new_values
                    ? '<pre class="small mb-0" style="white-space:pre-wrap;font-size:.7rem;max-width:200px">'
                      . e(json_encode($l->new_values, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))
                      . '</pre>'
                    : '<span class="text-muted">—</span>')
                ->filterColumn('user_name', fn($q, $k) =>
                    $q->whereHas('user', fn($u) => $u->where('name', 'like', "%$k%")))
                ->filterColumn('model_name', fn($q, $k) =>
                    $q->where('auditable_type', 'like', "%$k%"))
                ->rawColumns(['action_badge', 'old_values_fmt', 'new_values_fmt'])
                ->make(true);
        }

        return view('audit_logs.index');
    }

    // ── private helpers ──────────────────────────────────────────────────────

    private function actionBadge(string $action): string
    {
        $map = [
            'created' => 'success',
            'updated' => 'primary',
            'deleted' => 'danger',
        ];
        $color = $map[$action] ?? 'secondary';
        return "<span class='badge bg-{$color}'>{$action}</span>";
    }
}
