@extends('layouts.app')
@section('page-title', 'شيك — ' . $check->check_number)

@section('content')
<div class="row justify-content-center">
<div class="col-xl-9 col-lg-11">

@include('components.alerts')

{{-- ── بطاقة رأس الشيك ── --}}
<div class="card mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-bank2 text-primary me-2"></i>
            {{ $check->check_number }}
            &nbsp;{!! $check->typeBadge() !!}
            &nbsp;{!! $check->statusBadge() !!}
        </h5>
        <a href="{{ route('checks.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-right me-1"></i> قائمة الشيكات
        </a>
    </div>

    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="small text-muted">الجهة</div>
                <div class="fw-semibold">{{ $check->partyName() }}</div>
            </div>
            <div class="col-md-4">
                <div class="small text-muted">رقم الشيك الورقي</div>
                <div class="fw-semibold">{{ $check->check_ref ?? '—' }}</div>
            </div>
            <div class="col-md-4">
                <div class="small text-muted">البنك</div>
                <div>{{ $check->bank_name ?? '—' }}
                    @if($check->bank_branch) <small class="text-muted">— فرع {{ $check->bank_branch }}</small> @endif
                    @if($check->account_number) <small class="text-muted">({{ $check->account_number }})</small> @endif
                </div>
                @if($check->endorsed_to_supplier_id)
                <div class="small text-muted mt-1">مُجيَّر إلى: <strong>{{ $check->endorsedToSupplier?->name ?? '—' }}</strong></div>
                @endif
                @if($check->foreign_amount)
                <div class="small text-muted mt-1">عملة أجنبية:
                    <strong>{{ number_format($check->foreign_amount, 2) }} {{ $check->currency?->code ?? '' }}</strong>
                    × {{ rtrim(rtrim(number_format($check->exchange_rate, 6), '0'), '.') }}
                </div>
                @endif
            </div>
            <div class="col-md-4">
                <div class="small text-muted">تاريخ الشيك</div>
                <div>{{ $check->check_date->format('Y-m-d') }}</div>
            </div>
            <div class="col-md-4">
                <div class="small text-muted">تاريخ الاستحقاق</div>
                <div class="{{ $check->due_date->isPast() && !$check->isTerminal() ? 'text-danger fw-bold' : '' }}">
                    {{ $check->due_date->format('Y-m-d') }}
                    @if($check->due_date->isPast() && !$check->isTerminal())
                        <span class="badge bg-danger">متأخر</span>
                    @endif
                </div>
            </div>
            <div class="col-md-4">
                <div class="small text-muted">المبلغ</div>
                <div class="fw-bold fs-5">{{ number_format($check->amount, 2) }} {{ $currency }}</div>
            </div>
            @if($check->branch)
            <div class="col-md-4">
                <div class="small text-muted">الفرع</div>
                <div>{{ $check->branch->name }}</div>
            </div>
            @endif
            <div class="col-md-4">
                <div class="small text-muted">أُنشئ بواسطة</div>
                <div>{{ $check->user?->name ?? '—' }}</div>
            </div>
            @if($check->notes)
            <div class="col-12">
                <div class="small text-muted">ملاحظات</div>
                <div>{{ $check->notes }}</div>
            </div>
            @endif
        </div>
    </div>
</div>

{{-- ── تغيير الحالة ── --}}
@if(!$check->isTerminal())
@can('checks.transition')
<div class="card mb-3 border-primary">
    <div class="card-header bg-primary bg-opacity-10">
        <h6 class="mb-0"><i class="bi bi-arrow-repeat me-1"></i> تحديث حالة الشيك</h6>
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
            @foreach($check->allowedTransitions() as $toStatus)
                @if($toStatus === 'endorsed') @continue @endif
                @php
                    $labels = [
                        'deposited' => ['إيداع في البنك',      'btn-primary',   'bi-bank'],
                        'cleared'   => ['مُقاصّ (تم التحصيل)', 'btn-success',   'bi-check-circle'],
                        'bounced'   => ['مرتجع / ملتوي',       'btn-danger',    'bi-x-circle'],
                        'received'  => ['إعادة إيداع (للتحصيل)', 'btn-info',    'bi-arrow-clockwise'],
                        'returned'  => ['أُعيد للمورد',         'btn-secondary', 'bi-arrow-return-right'],
                    ];
                    $lbl = $labels[$toStatus] ?? [$toStatus, 'btn-secondary', 'bi-arrow-right'];
                @endphp
                <form action="{{ route('checks.transition', $check) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('هل تريد تغيير حالة الشيك إلى: {{ $lbl[0] }}؟')">
                    @csrf
                    <input type="hidden" name="to_status" value="{{ $toStatus }}">
                    <button type="submit" class="btn {{ $lbl[1] }}">
                        <i class="bi {{ $lbl[2] }} me-1"></i> {{ $lbl[0] }}
                    </button>
                </form>
            @endforeach
        </div>
        <p class="text-muted small mt-2 mb-0">
            <i class="bi bi-info-circle me-1"></i>
            كل تغيير في الحالة يُرحَّل قيد محاسبي تلقائياً.
        </p>

        {{-- تجيير الشيك لمورد (Endorsement) — لشيك وارد تحت التحصيل فقط --}}
        @if($check->type === 'receivable' && $check->status === 'received' && $suppliers->isNotEmpty())
        <hr>
        <form action="{{ route('checks.endorse', $check) }}" method="POST"
              onsubmit="return confirm('تجيير الشيك للمورد المحدد؟ سيُسدَّد من ذمته ويُرحَّل القيد.')">
            @csrf
            <label class="form-label small fw-bold"><i class="bi bi-arrow-left-right me-1"></i> تجيير الشيك لمورد (سداد ذمة)</label>
            <div class="input-group">
                <select name="supplier_id" class="form-select" required>
                    <option value="">— اختر المورد —</option>
                    @foreach($suppliers as $s)
                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-dark"><i class="bi bi-arrow-left-right me-1"></i> تجيير</button>
            </div>
            <small class="text-muted">القيد: مدين ذمم الموردين / دائن شيكات تحت التحصيل.</small>
        </form>
        @endif
    </div>
</div>
@endcan
@endif

{{-- ── القيود المحاسبية ── --}}
<div class="card">
    <div class="card-header bg-white">
        <h6 class="mb-0"><i class="bi bi-journal-text text-secondary me-2"></i>القيود المحاسبية المرتبطة</h6>
    </div>
    <div class="card-body p-0">
        @php
            $entries = collect([
                ['label' => 'قيد الاستلام / الإصدار', 'entry' => $check->journalEntry],
                ['label' => 'قيد الإيداع',             'entry' => $check->depositJournalEntry],
                ['label' => 'قيد المقاصة / الارتداد', 'entry' => $check->clearingJournalEntry],
            ])->filter(fn($e) => $e['entry'] !== null);
        @endphp

        @forelse($entries as $row)
        <div class="border-bottom px-3 py-2">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="badge bg-secondary">{{ $row['label'] }}</span>
                <span class="small text-muted">{{ $row['entry']->entry_date ?? '' }}</span>
            </div>
            <table class="table table-sm table-borderless mb-0">
                <thead class="table-light">
                    <tr>
                        <th>الحساب</th>
                        <th class="text-end">مدين</th>
                        <th class="text-end">دائن</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($row['entry']->lines as $line)
                    <tr>
                        <td>{{ $line->account?->code }} — {{ $line->account?->name }}</td>
                        <td class="text-end">{{ $line->debit  > 0 ? number_format($line->debit,  2) : '—' }}</td>
                        <td class="text-end">{{ $line->credit > 0 ? number_format($line->credit, 2) : '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @empty
        <p class="text-muted text-center py-3 mb-0">لا توجد قيود محاسبية مرتبطة بعد.</p>
        @endforelse
    </div>
</div>

{{-- زر الحذف --}}
@if(in_array($check->status, ['received', 'pending']))
@can('checks.delete')
<div class="mt-3">
    <form action="{{ route('checks.destroy', $check) }}" method="POST" class="d-inline"
          onsubmit="return confirm('هل تريد حذف هذا الشيك وعكس قيده؟')">
        @csrf @method('DELETE')
        <button type="submit" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-trash me-1"></i> حذف الشيك
        </button>
    </form>
</div>
@endcan
@endif

</div>
</div>
@endsection
