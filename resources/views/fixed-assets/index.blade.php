@extends('layouts.app')
@section('page-title', 'الأصول الثابتة')

@section('content')
<div class="card">
    <div class="card-header bg-white">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-building-gear text-primary me-2"></i>سجل الأصول الثابتة
            </h5>
            <div class="d-flex gap-2">
                @can('fixed_assets.depreciate')
                <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#depreciateAllModal">
                    <i class="bi bi-calendar-minus me-1"></i> تشغيل استهلاك الشهر
                </button>
                @endcan
                @can('fixed_assets.create')
                <a href="{{ route('fixed-assets.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle me-1"></i> أصل جديد
                </a>
                @endcan
            </div>
        </div>
    </div>

    @if(isset($branches) && $branches->count() >= 1)
    <div class="card-body border-bottom py-2">
        <form method="GET" class="row g-2 align-items-end" id="filterForm">
            @include('components.branch-filter')
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-funnel"></i>
                </button>
            </div>
        </form>
    </div>
    @endif

    <div class="card-body">
        <div class="table-responsive">
            <table id="fa-table" class="table table-hover" style="width:100%"
                   data-url="{{ route('fixed-assets.index') }}">
                <thead class="table-light">
                <tr>
                    <th>كود الأصل</th>
                    <th>الاسم</th>
                    <th>الفئة</th>
                    <th>الفرع</th>
                    <th class="text-end">التكلفة</th>
                    <th class="text-end">الاستهلاك المتراكم</th>
                    <th class="text-end">القيمة الدفترية</th>
                    <th class="text-center">الحالة</th>
                    <th class="text-center">إجراءات</th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

{{-- Depreciate All Modal --}}
@can('fixed_assets.depreciate')
<div class="modal fade" id="depreciateAllModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-calendar-minus text-warning me-2"></i>تشغيل استهلاك شهري لجميع الأصول
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('fixed-assets.depreciate-all') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-warning small">
                        سيُرحَّل قيد استهلاك لكل أصل نشط لم يُستهلك في الفترة المحددة.
                        العملية <strong>idempotent</strong> — لن تُنشئ قيوداً مكررة.
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <label class="form-label">السنة</label>
                            <input type="number" name="period_year" class="form-control"
                                   value="{{ date('Y') }}" min="2000" max="2100" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">الشهر</label>
                            <select name="period_month" class="form-select" required>
                                @foreach(['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'] as $i => $m)
                                    <option value="{{ $i+1 }}" {{ date('n') == $i+1 ? 'selected':'' }}>{{ $m }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-play-fill me-1"></i> تشغيل الاستهلاك
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan
@endsection

@section('scripts')
<script>
$(function() {
    $('#fa-table').DataTable($.extend(true, {}, window.dtDefaults, {
        processing: true,
        serverSide: true,
        ajax: {
            url: $('#fa-table').data('url'),
            data: function(d) {
                @if(isset($branchId) && $branchId) d.branch_id = {{ $branchId }}; @endif
            }
        },
        columns: [
            { data: 'asset_code' },
            { data: 'name' },
            { data: 'category_name' },
            { data: 'branch_name' },
            { data: 'cost_fmt',  className: 'text-end' },
            { data: 'accum_fmt', className: 'text-end' },
            { data: 'nbv_fmt',   className: 'text-end' },
            { data: 'status_badge', className: 'text-center', orderable: false },
            { data: 'action',       className: 'text-center', orderable: false },
        ],
    }));
});
</script>
@endsection
