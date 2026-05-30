@extends('layouts.app')
@section('title', 'الورديات')
@section('page-title', 'إدارة الورديات')

@section('content')
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-clock"></i> الورديات</h5>
        <a href="{{ route('hr.shifts.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle"></i> إضافة وردية
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover dt-table align-middle" style="width:100%">
                <thead class="table-light">
                    <tr>
                        <th>اسم الوردية</th>
                        <th>من</th>
                        <th>إلى</th>
                        <th>الساعات</th>
                        <th>الحالة</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($shifts as $shift)
                    <tr>
                        <td class="fw-semibold">{{ $shift->name }}</td>
                        <td>{{ $shift->start_time }}</td>
                        <td>{{ $shift->end_time }}</td>
                        <td>{{ $shift->hours }} ساعة</td>
                        <td>
                            @if($shift->is_active)
                                <span class="badge bg-success">نشطة</span>
                            @else
                                <span class="badge bg-secondary">موقوفة</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('hr.shifts.edit', $shift) }}" class="btn btn-sm btn-primary btn-action">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" action="{{ route('hr.shifts.destroy', $shift) }}" class="d-inline"
                                  onsubmit="return confirm('هل تريد حذف هذه الوردية؟')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger btn-action">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
