@extends('layouts.app')
@section('page-title', 'نقاط البيع (POS Terminals)')

@section('content')
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-cash-register text-primary me-2"></i>نقاط البيع وتعيين المخازن
        </h5>
        @can('pos_terminals.manage')
        <a href="{{ route('pos-terminals.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle me-1"></i> نقطة بيع جديدة
        </a>
        @endcan
    </div>
    <div class="card-body">

        <div class="alert alert-light border-start border-primary border-3 mb-3 small">
            <i class="bi bi-info-circle me-1"></i>
            كل نقطة بيع مرتبطة بمخزن محدد — عند البيع تُخصَّم الكمية من هذا المخزن فقط.
            <br>
            <strong>مثال المول:</strong> كاشير الطابق الأرضي → مخزن المعرض (floor) &nbsp;|&nbsp;
            كاشير الجملة → مخزن الخلفي (main)
        </div>

        <div class="table-responsive">
            <table class="table table-hover dt-table" style="width:100%">
                <thead class="table-light">
                <tr>
                    <th>الكود</th>
                    <th>الاسم</th>
                    <th>الفرع</th>
                    <th>
                        <i class="bi bi-archive text-success me-1"></i>
                        المخزن (مصدر الخصم)
                    </th>
                    <th>قائمة الأسعار</th>
                    <th class="text-center">مستخدمون</th>
                    <th class="text-center">الحالة</th>
                    <th class="text-center">إجراءات</th>
                </tr>
                </thead>
                <tbody>
                @foreach($terminals as $terminal)
                <tr class="{{ !$terminal->is_active ? 'opacity-50' : '' }}">
                    <td><code>{{ $terminal->code }}</code></td>
                    <td class="fw-semibold">{{ $terminal->name }}</td>
                    <td>{{ $terminal->branch?->name ?? '—' }}</td>
                    <td>
                        @if($terminal->warehouse)
                            <span class="badge bg-{{ $terminal->warehouse->type === 'floor' ? 'success' : ($terminal->warehouse->type === 'main' ? 'primary' : 'secondary') }} me-1">
                                {{ ['floor'=>'معرض','main'=>'رئيسي','returns'=>'مرتجعات'][$terminal->warehouse->type] ?? $terminal->warehouse->type }}
                            </span>
                            {{ $terminal->warehouse->name }}
                        @else
                            <span class="text-danger">غير مُعيَّن</span>
                        @endif
                    </td>
                    <td class="small text-muted">{{ $terminal->priceList?->name ?? '—' }}</td>
                    <td class="text-center">
                        <span class="badge bg-light text-dark border">{{ $terminal->users_count }}</span>
                    </td>
                    <td class="text-center">
                        @if($terminal->is_active)
                            <span class="badge bg-success">نشطة</span>
                        @else
                            <span class="badge bg-secondary">موقوفة</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @can('pos_terminals.manage')
                        <a href="{{ route('pos-terminals.edit', $terminal) }}" class="btn btn-sm btn-primary btn-action">
                            <i class="bi bi-pencil"></i>
                        </a>
                        @if($terminal->users_count === 0)
                        <form action="{{ route('pos-terminals.destroy', $terminal) }}" method="POST" class="d-inline"
                              onsubmit="return confirm('حذف نقطة البيع؟')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger btn-action"><i class="bi bi-trash"></i></button>
                        </form>
                        @endif
                        @endcan
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
