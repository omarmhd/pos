{{--
    Reusable branch filter component.
    Usage: @include('components.branch-filter', ['branches' => $branches, 'branchId' => $branchId])
    Shows whenever at least one branch exists.
--}}
@if(isset($branches) && $branches->count() >= 1)
<div class="col-auto">
    <label class="form-label small mb-1">
        <i class="bi bi-building-fill-check text-primary me-1"></i>الفرع
    </label>
    <select name="branch_id" class="form-select form-select-sm" style="min-width:200px"
            onchange="this.form.submit()">
        <option value="">جميع الفروع (موحد)</option>
        @foreach($branches as $b)
            <option value="{{ $b->id }}" {{ ($branchId ?? null) == $b->id ? 'selected' : '' }}>
                [{{ $b->code }}] {{ $b->name }}
            </option>
        @endforeach
    </select>
</div>
@endif
