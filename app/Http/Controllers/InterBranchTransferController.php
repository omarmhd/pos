<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\InterBranchTransfer;
use App\Models\Setting;
use App\Services\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InterBranchTransferController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:branches.manage');
    }

    public function index(Request $request)
    {
        $branchId = $this->effectiveBranchId($request);
        $currency = Setting::get('currency_symbol', 'ج.م');
        $branches = Branch::where('is_active', true)->orderBy('name')->get(['id','name','code']);

        $transfers = InterBranchTransfer::with('fromBranch', 'toBranch', 'createdBy')
            ->when($branchId, fn($q) =>
                $q->where('from_branch_id', $branchId)
                  ->orWhere('to_branch_id', $branchId))
            ->orderByDesc('transfer_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('accounting.inter-branch.index', compact(
            'transfers', 'branches', 'branchId', 'currency'
        ));
    }

    public function create()
    {
        $branches = Branch::where('is_active', true)->orderBy('name')->get(['id','name','code']);
        $currency = Setting::get('currency_symbol', 'ج.م');
        return view('accounting.inter-branch.create', compact('branches', 'currency'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'from_branch_id' => 'required|exists:branches,id',
            'to_branch_id'   => 'required|exists:branches,id|different:from_branch_id',
            'amount'         => 'required|numeric|min:0.01',
            'transfer_date'  => 'required|date',
            'reference'      => 'nullable|string|max:100',
            'description'    => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            $transfer = InterBranchTransfer::create([
                'from_branch_id' => $request->from_branch_id,
                'to_branch_id'   => $request->to_branch_id,
                'amount'         => $request->amount,
                'transfer_date'  => $request->transfer_date,
                'reference'      => $request->reference,
                'description'    => $request->description,
                'created_by'     => auth()->id(),
            ]);

            // Load branches for GL description labels
            $transfer->load('fromBranch', 'toBranch');

            // Post two GL entries (one per branch) — the core of inter-branch accounting
            $entries = (new LedgerPostingService())->postInterBranchTransfer($transfer);

            $transfer->update([
                'from_journal_entry_id' => $entries['from']->id,
                'to_journal_entry_id'   => $entries['to']->id,
            ]);

            DB::commit();

            $currency = Setting::get('currency_symbol', 'ج.م');
            return redirect()->route('inter-branch.index')
                ->with('success',
                    'تم تسجيل التحويل البيني بنجاح — '
                    . number_format($request->amount, 2) . ' ' . $currency
                    . ' من ' . $transfer->fromBranch->name
                    . ' إلى ' . $transfer->toBranch->name
                );

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'خطأ: ' . $e->getMessage());
        }
    }

    public function show(InterBranchTransfer $interBranch)
    {
        $interBranch->load(
            'fromBranch', 'toBranch', 'createdBy',
            'fromJournalEntry.lines.account',
            'toJournalEntry.lines.account'
        );
        $currency = Setting::get('currency_symbol', 'ج.م');
        return view('accounting.inter-branch.show', compact('interBranch', 'currency'));
    }
}
