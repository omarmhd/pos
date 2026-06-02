@extends('layouts.app')
@section('page-title', 'تفاصيل مرتجع مشتريات')

@section('content')
<div class="row">
    <div class="col-lg-10 mx-auto">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-arrow-return-right text-warning me-2"></i>
                    مرتجع مشتريات: {{ $purchaseReturn->return_number }}
                </h5>
                <a href="{{ route('purchase-returns.index') }}" class="btn btn-secondary btn-sm">
                    <i class="bi bi-arrow-right"></i> رجوع
                </a>
            </div>
            <div class="card-body">

                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-2">معلومات المرتجع</h6>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <th class="text-muted" style="width:45%">رقم المرتجع:</th>
                                <td><strong>{{ $purchaseReturn->return_number }}</strong></td>
                            </tr>
                            <tr>
                                <th class="text-muted">التاريخ:</th>
                                <td>{{ $purchaseReturn->return_date->format('Y-m-d') }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted">طريقة الاسترداد:</th>
                                <td>
                                    @if($purchaseReturn->refund_method === 'ap_deduction')
                                        <span class="badge bg-warning text-dark">خصم من ذمة المورد</span>
                                    @elseif($purchaseReturn->refund_method === 'cash')
                                        <span class="badge bg-success">استرداد نقدي</span>
                                    @else
                                        <span class="badge bg-info text-dark">استرداد بنكي</span>
                                    @endif
                                </td>
                            </tr>
                            @if($purchaseReturn->purchase)
                            <tr>
                                <th class="text-muted">الفاتورة الأصلية:</th>
                                <td>
                                    <a href="{{ route('purchases.show', $purchaseReturn->purchase) }}">
                                        {{ $purchaseReturn->purchase->invoice_number }}
                                    </a>
                                </td>
                            </tr>
                            @endif
                            <tr>
                                <th class="text-muted">المستخدم:</th>
                                <td>{{ $purchaseReturn->user->name }}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted mb-2">معلومات المورد</h6>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <th class="text-muted" style="width:40%">الاسم:</th>
                                <td><strong>{{ $purchaseReturn->supplier->name }}</strong></td>
                            </tr>
                            @if($purchaseReturn->supplier->company)
                            <tr>
                                <th class="text-muted">الشركة:</th>
                                <td>{{ $purchaseReturn->supplier->company }}</td>
                            </tr>
                            @endif
                        </table>
                        @if($purchaseReturn->notes)
                        <div class="alert alert-light border mt-2">
                            <i class="bi bi-sticky me-1"></i>
                            <strong>ملاحظات:</strong> {{ $purchaseReturn->notes }}
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Items --}}
                <div class="table-responsive mb-4">
                    <table class="table table-bordered">
                        <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>المنتج</th>
                            <th class="text-center">الكمية</th>
                            <th class="text-end">سعر الوحدة</th>
                            <th class="text-end">الإجمالي</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($purchaseReturn->items as $i => $item)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $item->product->name }}</td>
                            <td class="text-center">{{ $item->quantity + 0 }}</td>
                            <td class="text-end">{{ number_format($item->unit_price, 2) }} {{ $currency }}</td>
                            <td class="text-end"><strong>{{ number_format($item->total_price, 2) }} {{ $currency }}</strong></td>
                        </tr>
                        @endforeach
                        </tbody>
                        <tfoot class="table-light">
                        <tr>
                            <td colspan="4" class="text-end fw-bold">إجمالي المرتجع:</td>
                            <td class="text-end fw-bold text-warning fs-5">
                                {{ number_format($purchaseReturn->total_amount, 2) }} {{ $currency }}
                            </td>
                        </tr>
                        </tfoot>
                    </table>
                </div>

                {{-- GL entry --}}
                @if($purchaseReturn->journalEntry)
                <div class="card bg-light">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">
                            <i class="bi bi-journal-text me-1"></i>
                            القيد المحاسبي — {{ $purchaseReturn->journalEntry->reference }}
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <thead class="table-secondary">
                            <tr>
                                <th>الحساب</th>
                                <th class="text-end">مدين</th>
                                <th class="text-end">دائن</th>
                                <th>البيان</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($purchaseReturn->journalEntry->lines as $line)
                            <tr>
                                <td>{{ $line->account->code }} — {{ $line->account->name }}</td>
                                <td class="text-end">{{ $line->debit  > 0 ? number_format($line->debit, 2).' '.$currency : '—' }}</td>
                                <td class="text-end">{{ $line->credit > 0 ? number_format($line->credit,2).' '.$currency : '—' }}</td>
                                <td class="text-muted small">{{ $line->line_description }}</td>
                            </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif

            </div>
        </div>
    </div>
</div>
@endsection
