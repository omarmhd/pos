{{--
    Reusable branch filter component.

    Behaviour:
    - branchLocked = true  → branch employee → read-only badge (cannot change)
    - branchLocked = false → admin/head-office → dropdown to pick any branch
    - no branches          → single-branch company → render nothing

    Required in scope: $branches, $branchId
    Optional:
      $branchLocked (default: false)
      $dtReload     (default: false) — set true on DataTables/AJAX pages so the
                                       select does NOT auto-submit the form; the
                                       page JS calls dtWireFilters() instead.
--}}
@php
    $locked   = $branchLocked ?? false;
    $dtReload = $dtReload      ?? false;
@endphp

@if(isset($branches) && $branches->count() >= 1)
    @if($locked)
        {{-- Branch employee: locked to their branch — badge only, no dropdown --}}
        @php $currentBranch = $branches->firstWhere('id', $branchId); @endphp
        <div class="col-auto d-flex align-items-end">
            <div>
                <label class="form-label small mb-1">
                    <i class="bi bi-building-fill-check text-primary me-1"></i>الفرع
                </label>
                <div class="d-flex align-items-center gap-1">
                    <span class="badge bg-primary px-3 py-2" style="font-size:.85rem">
                        <i class="bi bi-lock-fill me-1 opacity-75"></i>
                        {{ $currentBranch->name ?? 'فرعي' }}
                    </span>
                    <input type="hidden" name="branch_id" value="{{ $branchId }}">
                </div>
            </div>
        </div>
    @else
        {{-- Admin / head-office: free to choose any branch --}}
        <div class="col-auto">
            <label class="form-label small mb-1">
                <i class="bi bi-building-fill-check text-primary me-1"></i>الفرع
            </label>
            <select name="branch_id" id="filterBranchId"
                    class="form-select form-select-sm" style="min-width:200px"
                    @if(!$dtReload) onchange="this.form.submit()" @endif>
                <option value="">جميع الفروع (موحد)</option>
                @foreach($branches as $b)
                    <option value="{{ $b->id }}" {{ ($branchId ?? null) == $b->id ? 'selected' : '' }}>
                        [{{ $b->code }}] {{ $b->name }}
                    </option>
                @endforeach
            </select>
        </div>
    @endif
@endif
