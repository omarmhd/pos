@extends('layouts.app')
@section('page-title', 'شجرة الحسابات')

@php
$typeConfig = [
    'asset'     => ['label' => 'الأصول',            'icon' => 'bi-bank',            'border' => 'border-primary',  'header_bg' => 'table-primary'],
    'liability' => ['label' => 'الالتزامات',         'icon' => 'bi-credit-card',     'border' => 'border-warning',  'header_bg' => 'table-warning'],
    'equity'    => ['label' => 'حقوق الملكية',       'icon' => 'bi-person-badge',    'border' => 'border-info',     'header_bg' => 'table-info'],
    'revenue'   => ['label' => 'الإيرادات',          'icon' => 'bi-graph-up-arrow',  'border' => 'border-success',  'header_bg' => 'table-success'],
    'expense'   => ['label' => 'المصروفات والتكاليف','icon' => 'bi-graph-down-arrow','border' => 'border-danger',   'header_bg' => 'table-danger'],
];
$typeMap  = ['asset'=>'أصل','liability'=>'التزام','equity'=>'حقوق ملكية','revenue'=>'إيراد','expense'=>'مصروف'];
$typeColor= ['asset'=>'primary','liability'=>'warning','equity'=>'info','revenue'=>'success','expense'=>'danger'];
@endphp

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-diagram-3"></i> شجرة الحسابات</h4>
    <div class="d-flex gap-2">
        @can('accounts.import')
        <a href="{{ route('accounts.import.form') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-upload"></i> استيراد CSV
        </a>
        <a href="{{ route('accounts.export') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download"></i> تصدير CSV
        </a>
        @endcan
        @can('accounts.create')
        <a href="{{ route('accounts.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle"></i> حساب جديد
        </a>
        @endcan
    </div>
</div>

@foreach($grouped as $type => $roots)
@php $cfg = $typeConfig[$type] ?? ['label'=>$type,'icon'=>'bi-circle','border'=>'border-secondary','header_bg'=>'table-secondary']; @endphp

<div class="card mb-4 {{ $cfg['border'] }} border-start border-4">
    {{-- Section header --}}
    <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center"
         style="cursor:pointer" data-bs-toggle="collapse"
         data-bs-target="#section-{{ $type }}" aria-expanded="true">
        <span class="fw-bold fs-6">
            <i class="bi {{ $cfg['icon'] }} me-1"></i>
            {{ $cfg['label'] }}
            <span class="badge bg-secondary ms-1 fw-normal" style="font-size:.7rem">
                {{ $roots->sum(fn($r) => 1 + $r->children->count()) }} حساب
            </span>
        </span>
        <i class="bi bi-chevron-up text-muted small section-chevron"></i>
    </div>

    <div class="collapse show" id="section-{{ $type }}">
        <table class="table table-sm table-hover mb-0">
            <thead class="{{ $cfg['header_bg'] }}">
                <tr>
                    <th style="width:110px">الكود</th>
                    <th>اسم الحساب</th>
                    <th style="width:90px">النوع</th>
                    <th style="width:70px" class="text-center">إجمالي</th>
                    <th style="width:70px" class="text-center">الحالة</th>
                    <th style="width:90px" class="text-center">إجراءات</th>
                </tr>
            </thead>
            <tbody>
            @foreach($roots as $account)
                @if($account->is_header)
                {{-- ── Header / group node ── --}}
                <tr class="account-header-row fw-semibold"
                    style="background:rgba(0,0,0,.03); cursor:pointer"
                    data-bs-toggle="collapse"
                    data-bs-target="#children-{{ $account->id }}">
                    <td>
                        <i class="bi bi-folder2-open text-muted me-1 small"></i>
                        <code class="fw-bold">{{ $account->code }}</code>
                    </td>
                    <td>
                        <span>{{ $account->name }}</span>
                        <span class="badge bg-secondary ms-1" style="font-size:.65rem">{{ $account->children->count() }} فرع</span>
                    </td>
                    <td>
                        <span class="badge bg-{{ $typeColor[$account->type] ?? 'secondary' }} opacity-75">
                            {{ $typeMap[$account->type] ?? $account->type }}
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-dark" style="font-size:.65rem">رئيسي</span>
                    </td>
                    <td class="text-center">
                        @if($account->is_active)
                            <i class="bi bi-check-circle-fill text-success"></i>
                        @else
                            <i class="bi bi-x-circle-fill text-secondary"></i>
                        @endif
                    </td>
                    <td class="text-center">
                        @can('accounts.edit')
                        <a href="{{ route('accounts.edit', $account->id) }}"
                           class="btn btn-sm btn-outline-primary btn-action">
                            <i class="bi bi-pencil"></i>
                        </a>
                        @endcan
                        @can('accounts.delete')
                        @if($account->children->isEmpty())
                        <form action="{{ route('accounts.destroy', $account->id) }}" method="POST"
                              class="d-inline" onsubmit="return confirm('حذف الحساب؟')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger btn-action">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        @endif
                        @endcan
                    </td>
                </tr>

                {{-- Children rows (collapsible) --}}
                <tr class="p-0">
                    <td colspan="6" class="p-0">
                        <div class="collapse show" id="children-{{ $account->id }}">
                            <table class="table table-sm table-hover mb-0">
                                <tbody>
                                @foreach($account->children->sortBy('code') as $child)
                                <tr>
                                    <td style="width:110px; padding-right:2rem">
                                        <i class="bi bi-dash text-muted"></i>
                                        <code>{{ $child->code }}</code>
                                    </td>
                                    <td>{{ $child->name }}</td>
                                    <td style="width:90px">
                                        <span class="badge bg-{{ $typeColor[$child->type] ?? 'secondary' }}">
                                            {{ $typeMap[$child->type] ?? $child->type }}
                                        </span>
                                    </td>
                                    <td style="width:70px" class="text-center">—</td>
                                    <td style="width:70px" class="text-center">
                                        @if($child->is_active)
                                            <i class="bi bi-check-circle-fill text-success"></i>
                                        @else
                                            <i class="bi bi-x-circle-fill text-secondary"></i>
                                        @endif
                                    </td>
                                    <td style="width:90px" class="text-center">
                                        @can('accounts.edit')
                                        <a href="{{ route('accounts.edit', $child->id) }}"
                                           class="btn btn-sm btn-outline-primary btn-action">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        @endcan
                                        @can('accounts.delete')
                                        <form action="{{ route('accounts.destroy', $child->id) }}" method="POST"
                                              class="d-inline" onsubmit="return confirm('حذف الحساب؟')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger btn-action">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                        @endcan
                                    </td>
                                </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </td>
                </tr>

                @else
                {{-- ── Regular leaf account (no parent) ── --}}
                <tr>
                    <td><code>{{ $account->code }}</code></td>
                    <td>{{ $account->name }}</td>
                    <td>
                        <span class="badge bg-{{ $typeColor[$account->type] ?? 'secondary' }}">
                            {{ $typeMap[$account->type] ?? $account->type }}
                        </span>
                    </td>
                    <td class="text-center">—</td>
                    <td class="text-center">
                        @if($account->is_active)
                            <i class="bi bi-check-circle-fill text-success"></i>
                        @else
                            <i class="bi bi-x-circle-fill text-secondary"></i>
                        @endif
                    </td>
                    <td class="text-center">
                        @can('accounts.edit')
                        <a href="{{ route('accounts.edit', $account->id) }}"
                           class="btn btn-sm btn-outline-primary btn-action">
                            <i class="bi bi-pencil"></i>
                        </a>
                        @endcan
                        @can('accounts.delete')
                        <form action="{{ route('accounts.destroy', $account->id) }}" method="POST"
                              class="d-inline" onsubmit="return confirm('حذف الحساب؟')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger btn-action">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        @endcan
                    </td>
                </tr>
                @endif
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endforeach

@endsection

@section('scripts')
<script>
// Flip chevron icon when section collapses/expands
document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(function(toggle) {
    var target = document.querySelector(toggle.dataset.bsTarget);
    if (!target) return;
    target.addEventListener('show.bs.collapse', function() {
        var icon = toggle.querySelector('.section-chevron');
        if (icon) { icon.classList.remove('bi-chevron-down'); icon.classList.add('bi-chevron-up'); }
    });
    target.addEventListener('hide.bs.collapse', function() {
        var icon = toggle.querySelector('.section-chevron');
        if (icon) { icon.classList.remove('bi-chevron-up'); icon.classList.add('bi-chevron-down'); }
    });
});
</script>
@endsection
