{{-- resources/views/suppliers/show.blade.php --}}
@extends('layouts.app')

@section('page-title', 'تفاصيل المورد')

@section('content')
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-truck"></i> تفاصيل المورد</h5>
                    <div class="d-flex gap-2">
                        <a href="{{ route('supplier-payments.create', $supplier) }}" class="btn btn-success btn-sm">
                            <i class="bi bi-cash-coin"></i> تسجيل دفعة
                        </a>
                        <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-primary btn-sm">
                            <i class="bi bi-pencil"></i> تعديل
                        </a>
                        <a href="{{ route('suppliers.index') }}" class="btn btn-secondary btn-sm">
                            <i class="bi bi-arrow-right"></i> رجوع
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h4>{{ $supplier->name }}</h4>
                            <p class="text-muted">{{ $supplier->company ?? 'لا يوجد اسم شركة' }}</p>

                            <table class="table table-borderless">
                                <tr>
                                    <th style="width: 40%">الهاتف:</th>
                                    <td>{{ $supplier->phone }}</td>
                                </tr>
                                <tr>
                                    <th>البريد الإلكتروني:</th>
                                    <td>{{ $supplier->email ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th>العنوان:</th>
                                    <td>{{ $supplier->address ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th>تاريخ الإضافة:</th>
                                    <td>{{ $supplier->created_at->format('Y-m-d') }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="card bg-light text-center h-100">
                                        <div class="card-body py-3">
                                            <h6 class="text-muted small">فواتير الشراء</h6>
                                            <h3 class="mb-0">{{ $supplier->purchases->count() }}</h3>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    @php $outstanding = $supplier->outstandingBalance(); @endphp
                                    <div class="card text-center h-100 border-{{ $outstanding > 0 ? 'danger' : 'success' }}">
                                        <div class="card-body py-3">
                                            <h6 class="text-muted small">المبلغ المستحق</h6>
                                            <h3 class="mb-0 text-{{ $outstanding > 0 ? 'danger' : 'success' }}">
                                                {{ number_format($outstanding, 2) }}
                                            </h3>
                                            <small class="text-muted">ج.م</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h5 class="mb-3"><i class="bi bi-receipt"></i> آخر فواتير الشراء</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                            <tr>
                                <th>رقم الفاتورة</th>
                                <th>المبلغ الإجمالي</th>
                                <th>المدفوع</th>
                                <th>المتبقي</th>
                                <th>حالة الدفع</th>
                                <th>التاريخ</th>
                                <th>إجراءات</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($supplier->purchases as $purchase)
                                <tr>
                                    <td><strong>{{ $purchase->invoice_number }}</strong></td>
                                    <td>{{ number_format($purchase->total_amount, 2) }} {{ $currency }}</td>
                                    <td>{{ number_format($purchase->paid_amount, 2) }} {{ $currency }}</td>
                                    <td>{{ number_format($purchase->remainingAmount(), 2) }} {{ $currency }}</td>
                                    <td>
                                        @if($purchase->payment_status == 'paid')
                                            <span class="badge bg-success">مدفوع</span>
                                        @elseif($purchase->payment_status == 'partial')
                                            <span class="badge bg-warning">جزئي</span>
                                        @else
                                            <span class="badge bg-danger">غير مدفوع</span>
                                        @endif
                                    </td>
                                    <td>{{ $purchase->created_at->format('Y-m-d') }}</td>
                                    <td>
                                        <a href="{{ route('purchases.show', $purchase) }}" class="btn btn-sm btn-info btn-action">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4">لا توجد فواتير شراء</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
