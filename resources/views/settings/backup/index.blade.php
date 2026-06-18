@extends('layouts.app')
@section('page-title', 'النسخ الاحتياطي')

@section('content')
<div class="row g-4">

    {{-- Controls --}}
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><strong><i class="bi bi-cloud-upload me-1"></i>إنشاء نسخة الآن</strong></div>
            <div class="card-body">
                <form action="{{ route('backup.run') }}" method="POST">
                    @csrf
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="notify" id="chkNotify" value="1" checked>
                        <label class="form-check-label" for="chkNotify">
                            إرسال إشعار بريدي عند الانتهاء
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"
                            onclick="return confirm('إنشاء نسخة احتياطية الآن؟ قد يستغرق ذلك دقيقة.')">
                        <i class="bi bi-play-fill me-1"></i> تشغيل النسخ الآن
                    </button>
                </form>
                <hr>
                <div class="small text-muted">
                    <i class="bi bi-database me-1"></i>
                    نسخة احتياطية لقاعدة البيانات فقط
                    <div class="mt-1">
                        <i class="bi bi-clock me-1"></i>
                        النسخ التلقائي: كل يوم الساعة 02:00 صباحاً
                    </div>
                    <div class="mt-1">
                        <i class="bi bi-trash me-1"></i>
                        يحتفظ بآخر 14 نسخة
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Backup list --}}
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-archive me-1"></i>النسخ الاحتياطية المتاحة</strong>
                <span class="badge bg-secondary">{{ $files->count() }} نسخة</span>
            </div>
            <div class="card-body p-0">
                @if($files->isEmpty())
                <div class="text-center text-muted py-5">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    لا توجد نسخ احتياطية — انقر "تشغيل النسخ الآن"
                </div>
                @else
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>اسم الملف</th>
                                <th class="text-center">الحجم</th>
                                <th>التاريخ</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($files as $f)
                        <tr>
                            <td>
                                <i class="bi bi-file-earmark-zip text-warning me-1"></i>
                                <span class="font-monospace small">{{ $f->name }}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border">{{ $f->size_mb }} MB</span>
                            </td>
                            <td class="text-muted small">{{ $f->modified->format('Y-m-d H:i') }}</td>
                            <td class="text-end">
                                <a href="{{ route('backup.download', $f->name) }}"
                                   class="btn btn-sm btn-outline-primary btn-action" title="تنزيل">
                                    <i class="bi bi-download"></i>
                                </a>
                                <form action="{{ route('backup.destroy', $f->name) }}" method="POST"
                                      class="d-inline" onsubmit="return confirm('حذف هذه النسخة الاحتياطية؟')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger btn-action" title="حذف">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>

        {{-- Log tail --}}
        @if(!empty($lastLines))
        <div class="card mt-3">
            <div class="card-header"><strong><i class="bi bi-terminal me-1"></i>آخر سجل تشغيل</strong></div>
            <div class="card-body p-0">
                <pre class="bg-dark text-success p-3 mb-0 small rounded-bottom"
                     style="max-height:200px;overflow-y:auto;font-size:.72rem">{{ implode("\n", $lastLines) }}</pre>
            </div>
        </div>
        @endif
    </div>

</div>
@endsection
