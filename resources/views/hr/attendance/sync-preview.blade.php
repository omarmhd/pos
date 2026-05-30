@extends('layouts.app')
@section('page-title', 'معاينة بيانات ZKTeco')

@section('content')
<div class="row">
    <div class="col-lg-10 mx-auto">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-eye"></i> معاينة البيانات المراد مزامنتها</h5>
                <a href="{{ route('hr.attendance.sync') }}" class="btn btn-secondary btn-sm">
                    <i class="bi bi-arrow-right"></i> رجوع
                </a>
            </div>
            <div class="card-body">

                @if(is_array($data) && count($data) > 0)
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    سيتم مزامنة <strong>{{ count($data) }}</strong> سجل حضور
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>رقم الموظف</th>
                                <th>وقت التسجيل</th>
                                <th>النوع</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data as $i => $record)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $record['employee_id'] ?? '—' }}</td>
                                <td>{{ \Carbon\Carbon::parse($record['check_time'])->format('Y-m-d H:i') }}</td>
                                <td>
                                    @if($record['status'] == 0)
                                        <span class="badge bg-success">دخول ✓</span>
                                    @else
                                        <span class="badge bg-danger">خروج ✗</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    <form action="{{ route('hr.attendance.sync-now') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle"></i> تأكيد المزامنة
                        </button>
                        <a href="{{ route('hr.attendance.sync') }}" class="btn btn-outline-secondary">
                            إلغاء
                        </a>
                    </form>
                </div>

                @else
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    لا توجد بيانات للمعاينة
                </div>
                <a href="{{ route('hr.attendance.sync') }}" class="btn btn-secondary">
                    رجوع
                </a>
                @endif

            </div>
        </div>
    </div>
</div>
@endsection
