<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:accounts.view')->only(['index', 'exportCsv']);
        $this->middleware('can:accounts.create')->only(['create', 'store']);
        $this->middleware('can:accounts.edit')->only(['edit', 'update']);
        $this->middleware('can:accounts.delete')->only(['destroy']);
        $this->middleware('can:accounts.import')->only(['importForm', 'importCsv']);
    }

    public function index()
    {
        // Load all root accounts (no parent) with their direct children, ordered by code.
        // Header accounts act as group nodes; leaf accounts carry the detail.
        $roots = Account::whereNull('parent_id')
            ->with(['children' => fn($q) => $q->orderBy('code')])
            ->orderBy('code')
            ->get();

        // Group roots by accounting type for section headers
        $typeOrder = ['asset' => 0, 'liability' => 1, 'equity' => 2, 'revenue' => 3, 'expense' => 4];
        $grouped = $roots->sortBy(fn($a) => $typeOrder[$a->type] ?? 9)
                         ->groupBy('type');

        return view('accounts.index', compact('grouped'));
    }

    public function exportCsv()
    {
        $accounts = Account::orderBy('code')->get();
        $filename = 'accounts_export_' . date('Ymd_His') . '.csv';
        $handle = fopen('php://memory', 'w');
        fputcsv($handle, ['code', 'name', 'type', 'is_active']);
        foreach ($accounts as $acc) {
            fputcsv($handle, [$acc->code, $acc->name, $acc->type, $acc->is_active ? 1 : 0]);
        }
        fseek($handle, 0);
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$filename",
        ];
        return response()->stream(function () use ($handle) {
            fpassthru($handle);
        }, 200, $headers);
    }

    public function importForm()
    {
        return view('accounts.import');
    }

    public function importCsv(Request $request)
    {
        $request->validate(['csv_file' => 'required|file']);
        $path = $request->file('csv_file')->getRealPath();
        if (($handle = fopen($path, 'r')) !== false) {
            $header = null;
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                if (!$header) { $header = $row; continue; }
                $data = array_combine($header, $row);
                Account::updateOrCreate(['code' => $data['code']], [
                    'name' => $data['name'] ?? '',
                    'type' => $data['type'] ?? 'asset',
                    'is_active' => isset($data['is_active']) ? (bool)$data['is_active'] : true,
                ]);
            }
            fclose($handle);
        }
        return redirect()->route('accounts.index')->with('success', 'Imported accounts');
    }

    public function create()
    {
        $parents = Account::where('is_header', true)->orderBy('code')->get(['id', 'code', 'name']);
        return view('accounts.create', compact('parents'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code'      => 'required|string|max:20|unique:accounts,code',
            'name'      => 'required|string|max:255',
            'type'      => 'required|in:asset,liability,equity,revenue,expense',
            'parent_id' => 'nullable|exists:accounts,id',
            'sub_type'  => 'nullable|string|max:30',
            'is_header' => 'boolean',
            'notes'     => 'nullable|string|max:500',
        ]);

        $data['is_header'] = $request->boolean('is_header');
        $data['is_active'] = true;

        Account::create($data);
        \App\Models\AuditLog::create([
            'user_id' => auth()->id(),
            'auditable_type' => Account::class,
            'auditable_id' => null,
            'action' => 'created',
            'old_values' => null,
            'new_values' => $data,
            'ip_address' => request()->ip() ?? null,
        ]);
        return redirect()->route('accounts.index')->with('success', 'Account created');
    }

    public function edit(Account $account)
    {
        $parents = Account::where('is_header', true)
            ->where('id', '!=', $account->id)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);
        return view('accounts.edit', compact('account', 'parents'));
    }

    public function update(Request $request, Account $account)
    {
        $data = $request->validate([
            'code'      => 'required|string|max:20|unique:accounts,code,' . $account->id,
            'name'      => 'required|string|max:255',
            'type'      => 'required|in:asset,liability,equity,revenue,expense',
            'parent_id' => 'nullable|exists:accounts,id',
            'sub_type'  => 'nullable|string|max:30',
            'is_header' => 'boolean',
            'notes'     => 'nullable|string|max:500',
        ]);

        $data['is_header'] = $request->boolean('is_header');

        $old = $account->toArray();
        $account->update($data);
        \App\Models\AuditLog::create([
            'user_id' => auth()->id(),
            'auditable_type' => Account::class,
            'auditable_id' => $account->id,
            'action' => 'updated',
            'old_values' => $old,
            'new_values' => $data,
            'ip_address' => request()->ip() ?? null,
        ]);
        return redirect()->route('accounts.index')->with('success', 'Account updated');
    }

    public function destroy(Account $account)
    {
        $old = $account->toArray();
        $account->delete();
        \App\Models\AuditLog::create([
            'user_id' => auth()->id(),
            'auditable_type' => Account::class,
            'auditable_id' => $account->id,
            'action' => 'deleted',
            'old_values' => $old,
            'new_values' => null,
            'ip_address' => request()->ip() ?? null,
        ]);
        return redirect()->route('accounts.index')->with('success', 'Account deleted');
    }
}
