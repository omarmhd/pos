@extends('layouts.app')

@section('page-title', 'تفاصيل المنتج')

@section('content')
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-box-seam"></i> تفاصيل المنتج</h5>
                    <div>
                        <a href="{{ route('products.edit', $product) }}" class="btn btn-primary btn-sm">
                            <i class="bi bi-pencil"></i> تعديل
                        </a>
                        <a href="{{ route('products.index') }}" class="btn btn-secondary btn-sm">
                            <i class="bi bi-arrow-right"></i> رجوع
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 text-center mb-4">
                            @if($product->image)
                                <img src="{{ asset('storage/' . $product->image) }}" class="img-fluid rounded" alt="{{ $product->name }}">
                            @else
                                <div class="bg-light rounded p-5">
                                    <i class="bi bi-box-seam" style="font-size: 5rem; color: #ccc;"></i>
                                </div>
                            @endif
                        </div>
                        <div class="col-md-8">
                            <h3>{{ $product->name }}</h3>
                            <p class="text-muted">{{ $product->description ?? 'لا يوجد وصف' }}</p>

                            <table class="table table-bordered">
                                <tr>
                                    <th style="width: 40%">الباركود:</th>
                                    <td><strong>{{ $product->barcode }}</strong></td>
                                </tr>
                                <tr>
                                    <th>الفئة:</th>
                                    <td><span class="badge bg-info">{{ $product->category->name }}</span></td>
                                </tr>
                                <tr>
                                    <th>الوحدة:</th>
                                    <td>
                                        {{ $product->unit instanceof \App\Enums\ProductUnit ? $product->unit->label() : $product->unit }}
                                    </td>
                                </tr>
                                <tr>
                                    <th>سعر الشراء:</th>
                                    <td>{{ number_format($product->cost_price, 2) }} {{ $currency }}</td>
                                </tr>
                                <tr>
                                    <th>سعر البيع:</th>
                                    <td class="text-success"><strong>{{ number_format($product->selling_price, 2) }} {{ $currency }}</strong></td>
                                </tr>
                                <tr>
                                    <th>هامش الربح:</th>
                                    <td>{{ number_format($product->selling_price - $product->cost_price, 2) }} {{ $currency }}
                                        ({{ number_format((($product->selling_price - $product->cost_price) / $product->cost_price) * 100, 1) }}%)
                                    </td>
                                </tr>
                                <tr>
                                    <th>الكمية المتاحة:</th>
                                    <td>
                                        @if($product->isLowStock())
                                            <span class="badge bg-danger fs-6">{{ $product->quantity }}</span>
                                            <small class="text-danger">(قليل!)</small>
                                        @else
                                            <span class="badge bg-success fs-6">{{ $product->quantity }}</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>الحد الأدنى:</th>
                                    <td>{{ $product->min_quantity }}</td>
                                </tr>
                                <tr>
                                    <th>تاريخ الصلاحية:</th>
                                    <td>
                                        @if($product->expiry_date)
                                            @if($product->isExpired())
                                                <span class="badge bg-danger">{{ $product->expiry_date->format('Y-m-d') }} (منتهي)</span>
                                            @elseif($product->isExpiringSoon())
                                                <span class="badge bg-warning text-dark">{{ $product->expiry_date->format('Y-m-d') }} (قريب)</span>
                                            @else
                                                <span class="badge bg-success">{{ $product->expiry_date->format('Y-m-d') }}</span>
                                            @endif
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>نوع الصنف:</th>
                                    <td><span class="badge bg-secondary">{{ $product->typeLabel() }}</span></td>
                                </tr>
                                <tr>
                                    <th>الحد الأقصى / حد إعادة الطلب:</th>
                                    <td>
                                        {{ $product->max_quantity !== null ? number_format($product->max_quantity, 2) : '—' }}
                                        /
                                        {{ $product->reorder_level !== null ? number_format($product->reorder_level, 2) : '—' }}
                                        @if($product->needsReorder())
                                            <span class="badge bg-warning text-dark">بلغ حد إعادة الطلب</span>
                                        @endif
                                        @if($product->isOverMax())
                                            <span class="badge bg-danger">تجاوز الحد الأقصى</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>الضريبة:</th>
                                    <td>
                                        @if($product->is_taxable)
                                            <span class="badge bg-success">خاضع</span>
                                            — النسبة: {{ $product->vat_rate !== null ? number_format($product->vat_rate, 2) . '%' : 'العامة' }}
                                        @else
                                            <span class="badge bg-secondary">معفى</span>
                                        @endif
                                    </td>
                                </tr>
                                @if($product->bonus_every_qty)
                                <tr>
                                    <th>البونص:</th>
                                    <td>
                                        {{ rtrim(rtrim(number_format($product->bonus_free_qty, 3), '0'), '.') }} مجاناً
                                        لكل {{ rtrim(rtrim(number_format($product->bonus_every_qty, 3), '0'), '.') }}
                                        @if($product->bonus_after_qty)
                                            (بعد {{ rtrim(rtrim(number_format($product->bonus_after_qty, 3), '0'), '.') }})
                                        @endif
                                    </td>
                                </tr>
                                @endif
                                @if($product->currency)
                                <tr>
                                    <th>عملة الأسعار:</th>
                                    <td>{{ $product->currency->name }} —
                                        شراء: {{ $product->cost_price_fc !== null ? number_format($product->cost_price_fc, 4) : '—' }} /
                                        بيع: {{ $product->selling_price_fc !== null ? number_format($product->selling_price_fc, 4) : '—' }}
                                    </td>
                                </tr>
                                @endif
                                <tr>
                                    <th>عدد الحركات / آخر حركة:</th>
                                    <td>{{ number_format($movementsCount) }}
                                        — {{ $lastMovementDate ? \Illuminate\Support\Carbon::parse($lastMovementDate)->format('Y-m-d H:i') : 'لا حركات' }}
                                    </td>
                                </tr>
                                <tr>
                                    <th>تاريخ الإضافة:</th>
                                    <td>{{ $product->created_at->format('Y-m-d H:i') }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    @if($product->units->count())
                    <h6 class="text-primary mt-3"><i class="bi bi-boxes"></i> الوحدات الإضافية</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr><th>الوحدة</th><th>المعامل</th><th>الباركود</th><th>سعر البيع</th><th>التكلفة</th></tr>
                            </thead>
                            <tbody>
                                @foreach($product->units as $u)
                                <tr>
                                    <td>{{ $u->name }}</td>
                                    <td>{{ rtrim(rtrim(number_format($u->factor, 4), '0'), '.') }} × {{ $product->unit instanceof \App\Enums\ProductUnit ? $product->unit->label() : $product->unit }}</td>
                                    <td class="font-monospace">{{ $u->barcode ?? '—' }}</td>
                                    <td>{{ number_format($u->effectiveSellingPrice(), 2) }} {{ $currency }}</td>
                                    <td>{{ number_format($u->effectiveCostPrice(), 2) }} {{ $currency }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif

                    @if($product->components->count())
                    <h6 class="text-primary mt-3"><i class="bi bi-diagram-3"></i> معادلة التصنيع / المكونات</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr><th>المكوّن</th><th>الكمية لكل وحدة</th><th>تكلفة المكوّن</th><th>الإجمالي</th></tr>
                            </thead>
                            <tbody>
                                @foreach($product->components as $c)
                                <tr>
                                    <td>{{ $c->component->name }}</td>
                                    <td>{{ rtrim(rtrim(number_format($c->quantity, 4), '0'), '.') }}</td>
                                    <td>{{ number_format($c->component->cost_price, 2) }} {{ $currency }}</td>
                                    <td>{{ number_format($c->quantity * $c->component->cost_price, 2) }} {{ $currency }}</td>
                                </tr>
                                @endforeach
                                <tr class="table-light fw-bold">
                                    <td colspan="3">تكلفة الوحدة المنتجة</td>
                                    <td>{{ number_format($product->componentsCost(), 2) }} {{ $currency }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

{{-- رسم بياني لحركات الصنف (كما في الأصيل) --}}
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white">
                <strong><i class="bi bi-bar-chart-line text-primary me-1"></i> رسم بياني لحركات الصنف — آخر 12 شهراً</strong>
            </div>
            <div class="card-body">
                <canvas id="movementsChart" height="90"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- Cost Price History --}}
@if(isset($costHistory) && $costHistory->count())
<div class="row mt-4">
    <div class="col-12">
        <div class="card border-warning">
            <div class="card-header bg-warning bg-opacity-10">
                <strong><i class="bi bi-graph-up text-warning me-1"></i>
                    سجل تغييرات سعر التكلفة ({{ $costHistory->count() }} تغيير)
                </strong>
                <span class="badge bg-secondary ms-2 small">AVCO — متوسط متحرك</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>التاريخ</th>
                                <th class="text-end">قبل</th>
                                <th class="text-center">→</th>
                                <th class="text-end">بعد</th>
                                <th class="text-end">التغيير</th>
                                <th class="text-center">الكمية</th>
                                <th>الطريقة</th>
                                <th>بواسطة</th>
                                <th>ملاحظات</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($costHistory as $h)
                        @php $diff = (float)$h->new_cost - (float)$h->old_cost; @endphp
                        <tr>
                            <td class="text-muted small">{{ $h->created_at->format('Y-m-d H:i') }}</td>
                            <td class="text-end font-monospace text-muted">{{ number_format($h->old_cost, 4) }}</td>
                            <td class="text-center text-muted">→</td>
                            <td class="text-end font-monospace fw-bold">{{ number_format($h->new_cost, 4) }} {{ $currency }}</td>
                            <td class="text-end font-monospace {{ $diff > 0 ? 'text-danger' : 'text-success' }}">
                                {{ $diff > 0 ? '+' : '' }}{{ number_format($diff, 4) }}
                            </td>
                            <td class="text-center text-muted small">
                                {{ $h->qty_received ? number_format($h->qty_received, 2) : '—' }}
                            </td>
                            <td><span class="badge bg-info small">{{ $h->methodLabel() }}</span></td>
                            <td class="text-muted small">{{ $h->changedBy?->name ?? 'النظام' }}</td>
                            <td class="text-muted" style="font-size:.7rem;max-width:200px;word-break:break-all">
                                {{ $h->notes ? Str::limit($h->notes, 80) : '—' }}
                            </td>
                        </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@section('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
    new Chart(document.getElementById('movementsChart'), {
        type: 'bar',
        data: {
            labels: @json($chartLabels),
            datasets: [
                {
                    label: 'وارد',
                    data: @json($chartIn),
                    backgroundColor: 'rgba(25, 135, 84, 0.6)',
                    borderColor: 'rgb(25, 135, 84)',
                    borderWidth: 1
                },
                {
                    label: 'صادر',
                    data: @json($chartOut),
                    backgroundColor: 'rgba(220, 53, 69, 0.6)',
                    borderColor: 'rgb(220, 53, 69)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top', rtl: true } },
            scales: { y: { beginAtZero: true } }
        }
    });
</script>
@endsection
