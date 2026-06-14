@extends('layouts.app')

@section('page-title', 'مستند تصنيع ' . $assembly->number)

@section('content')
<div class="row">
    <div class="col-lg-10 mx-auto">
        <div class="card mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-gear-wide-connected"></i> مستند تصنيع: {{ $assembly->number }}</h5>
                <div>
                    @if($assembly->is_posted)
                        <span class="badge bg-success">مُرحَّل محاسبياً</span>
                    @endif
                    <a href="{{ route('assemblies.index') }}" class="btn btn-secondary btn-sm">
                        <i class="bi bi-arrow-right"></i> رجوع
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3"><strong>التاريخ:</strong> {{ $assembly->assembly_date->format('Y-m-d') }}</div>
                    <div class="col-md-3"><strong>المخزن:</strong> {{ $assembly->warehouse?->name ?? '—' }}</div>
                    <div class="col-md-3"><strong>المستخدم:</strong> {{ $assembly->user?->name ?? '—' }}</div>
                    <div class="col-md-3"><strong>ملاحظات:</strong> {{ $assembly->notes ?? '—' }}</div>
                </div>

                <div class="alert alert-success d-flex justify-content-between">
                    <div>
                        <strong>المنتَج:</strong>
                        <a href="{{ route('products.show', $assembly->product) }}">{{ $assembly->product->name }}</a>
                        × {{ number_format($assembly->quantity, 3) }}
                    </div>
                    <div>
                        <strong>تكلفة الوحدة:</strong> {{ number_format($assembly->unit_cost, 4) }} {{ $currency }}
                        &nbsp;|&nbsp;
                        <strong>الإجمالي:</strong> {{ number_format($assembly->total_cost, 2) }} {{ $currency }}
                    </div>
                </div>

                <h6 class="text-primary">المكونات المستهلكة</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>المكوّن</th>
                                <th>الكمية</th>
                                <th>تكلفة الوحدة</th>
                                <th>الإجمالي ({{ $currency }})</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($assembly->items as $item)
                            <tr>
                                <td><a href="{{ route('products.show', $item->component) }}">{{ $item->component->name }}</a></td>
                                <td>{{ number_format($item->quantity, 4) }}</td>
                                <td>{{ number_format($item->unit_cost, 4) }}</td>
                                <td>{{ number_format($item->total_cost, 2) }}</td>
                            </tr>
                            @endforeach
                            <tr class="table-light fw-bold">
                                <td colspan="3">الإجمالي</td>
                                <td>{{ number_format($assembly->total_cost, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if($journalEntry)
        <div class="card">
            <div class="card-header bg-white">
                <strong><i class="bi bi-journal-text text-primary"></i> القيد المحاسبي
                    — <a href="{{ route('journal_entries.show', $journalEntry) }}">{{ $journalEntry->reference }}</a>
                </strong>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>الحساب</th><th>البيان</th><th class="text-end">مدين</th><th class="text-end">دائن</th></tr>
                    </thead>
                    <tbody>
                        @foreach($journalEntry->lines as $line)
                        <tr>
                            <td>{{ $line->account?->code }} — {{ $line->account?->name }}</td>
                            <td class="text-muted small">{{ $line->line_description }}</td>
                            <td class="text-end">{{ $line->debit > 0 ? number_format($line->debit, 2) : '—' }}</td>
                            <td class="text-end">{{ $line->credit > 0 ? number_format($line->credit, 2) : '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
