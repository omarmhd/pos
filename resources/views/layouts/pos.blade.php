<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>نقطة البيع</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    @yield('styles')
</head>
<body style="font-family:'Cairo',sans-serif; background:#f0f2f5; height:100dvh; overflow:hidden;">

    @yield('content')

    {{-- Toast container --}}
    <div class="toast-container position-fixed top-0 start-50 translate-middle-x p-3" style="z-index:9999">
        <div id="posToast" class="toast align-items-center border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body fw-semibold" id="posToastBody"></div>
                <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    @yield('scripts')

    <div style="position:fixed;bottom:0;left:0;right:0;text-align:center;font-size:0.72rem;
                color:rgba(0,0,0,0.35);padding:3px 0;background:rgba(255,255,255,0.7);
                backdrop-filter:blur(4px);z-index:100;border-top:1px solid rgba(0,0,0,0.07);">
        <strong>الميزان</strong> — نظام إدارة متكامل للمحلات التجارية
        &nbsp;|&nbsp; النسخة التجريبية
        &nbsp;|&nbsp; للتواصل والدعم الفني عبر واتساب:
        <a href="https://wa.me/970592676623" target="_blank"
           style="color:#16a34a;text-decoration:none;font-weight:600;">
            <i class="bi bi-whatsapp"></i> ‎+970 592 676 623
        </a>
    </div>
</body>
</html>
