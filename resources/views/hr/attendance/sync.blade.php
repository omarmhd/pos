@extends('layouts.app')
@section('page-title', 'مزامنة بيانات الحضور')

@section('content')
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-arrow-repeat"></i> مزامنة ZKTeco</h5>
                <a href="{{ route('hr.employees.index') }}" class="btn btn-secondary btn-sm">
                    <i class="bi bi-arrow-right"></i> عودة
                </a>
            </div>
            <div class="card-body">

                {{-- Info Section --}}
                <div class="alert alert-info mb-4">
                    <i class="bi bi-info-circle"></i>
                    <strong>الوضع الحالي:</strong>
                    @php $isBadgeWarning = $zkMode === 'demo'; @endphp
                    <span class="badge {{ $isBadgeWarning ? 'bg-warning' : 'bg-success' }}">
                        {{ $zkMode === 'demo' ? 'Demo (تجريبي)' : 'Real (فعلي)' }}
                    </span>
                    <p class="mt-2 mb-0">
                        إذا كنت في وضع <strong>Demo</strong>، ستحصل على بيانات وهمية للاختبار.
                        غيّر الوضع إلى <strong>Real</strong> عند ربط جهاز ZKTeco فعلي.
                    </p>
                </div>

                {{-- Settings --}}
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label"><strong>IP Address</strong></label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="zkIp" value="{{ \App\Models\Setting::get('zkteco_ip', '192.168.1.100') }}" readonly>
                            <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#settingsModal">
                                <i class="bi bi-pencil"></i> تعديل
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><strong>Port</strong></label>
                        <input type="text" class="form-control" value="{{ \App\Models\Setting::get('zkteco_port', '23') }}" readonly>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="row g-2">
                    <div class="col-md-4">
                        <button class="btn btn-primary w-100" onclick="syncNow()">
                            <i class="bi bi-arrow-clockwise"></i> مزامنة الآن
                        </button>
                    </div>
                    <div class="col-md-4">
                        <a href="{{ route('hr.attendance.sync-preview') }}" class="btn btn-outline-primary w-100">
                            <i class="bi bi-eye"></i> معاينة
                        </a>
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-outline-secondary w-100" data-bs-toggle="modal" data-bs-target="#settingsModal">
                            <i class="bi bi-gear"></i> الإعدادات
                        </button>
                    </div>
                </div>

                {{-- Result Messages --}}
                @if(session('success'))
                <div class="alert alert-success mt-4" role="alert">
                    <i class="bi bi-check-circle"></i> {{ session('success') }}
                </div>
                @endif

                @if(session('error'))
                <div class="alert alert-danger mt-4" role="alert">
                    <i class="bi bi-exclamation-circle"></i> {{ session('error') }}
                </div>
                @endif

            </div>
        </div>

        {{-- Last Sync Info --}}
        <div class="card mt-3">
            <div class="card-header bg-light">
                <h6 class="mb-0"><i class="bi bi-history"></i> معلومات المزامنة</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <tr>
                        <td><strong>الوضع:</strong></td>
                        <td>
                            <span class="badge {{ $zkMode === 'demo' ? 'bg-warning' : 'bg-success' }}">
                                {{ $zkMode === 'demo' ? 'Demo' : 'Real' }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>مفعّل:</strong></td>
                        <td>{{ $zkEnabled ? '✅ نعم' : '❌ لا' }}</td>
                    </tr>
                    <tr>
                        <td><strong>الجهاز:</strong></td>
                        <td>{{ \App\Models\Setting::get('zkteco_ip', '—') }}:{{ \App\Models\Setting::get('zkteco_port', '—') }}</td>
                    </tr>
                </table>
            </div>
        </div>

    </div>
</div>

{{-- Settings Modal --}}
<div class="modal fade" id="settingsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">إعدادات ZKTeco</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="settingsForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">الوضع</label>
                        <select class="form-select" id="zkMode">
                            <option value="demo" {{ \App\Models\Setting::get('zkteco_mode', 'demo') === 'demo' ? 'selected' : '' }}>Demo (تجريبي)</option>
                            <option value="real" {{ \App\Models\Setting::get('zkteco_mode', 'demo') === 'real' ? 'selected' : '' }}>Real (فعلي)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">IP Address</label>
                        <input type="text" class="form-control" id="zkIpInput" value="{{ \App\Models\Setting::get('zkteco_ip', '192.168.1.100') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Port</label>
                        <input type="text" class="form-control" id="zkPort" value="{{ \App\Models\Setting::get('zkteco_port', '23') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username (اختياري)</label>
                        <input type="text" class="form-control" id="zkUsername" value="{{ \App\Models\Setting::get('zkteco_username', '') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password (اختياري)</label>
                        <input type="password" class="form-control" id="zkPassword" value="{{ \App\Models\Setting::get('zkteco_password', '') }}">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-primary" onclick="saveSettings()">حفظ الإعدادات</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function syncNow() {
    if (!confirm('هل تريد مزامنة البيانات من جهاز ZKTeco؟')) return;

    fetch('{{ route("hr.attendance.sync-now") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        }
    })
    .then(r => r.json())
    .then(data => {
        const msg = `✅ تمت مزامنة ${data.synced} سجل`;
        alert(msg);
        location.reload();
    })
    .catch(err => alert('خطأ: ' + err.message));
}

function saveSettings() {
    alert('سيتم حفظ الإعدادات قريباً');
    bootstrap.Modal.getInstance(document.getElementById('settingsModal')).hide();
}
</script>
@endsection
