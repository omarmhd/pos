<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'تسجيل الدخول') — {{ \App\Models\Setting::get('system_name', 'الميّزان') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/theme.css', 'resources/css/auth.css', 'resources/js/app.js'])
    <style>
        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            min-height: 100%;
        }

        body.auth-page {
            font-family: 'Cairo', sans-serif;
            color: #0f172a;
            background:
                radial-gradient(circle at top right, rgba(217, 119, 6, 0.16), transparent 28%),
                radial-gradient(circle at bottom left, rgba(15, 23, 42, 0.2), transparent 32%),
                linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
        }

        .auth-shell {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .auth-panel {
            width: 100%;
            display: grid;
            place-items: center;
        }

        .auth-card {
            width: 100%;
            max-width: 640px;
            padding: 44px 36px;
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid rgba(148, 163, 184, 0.16);
            box-shadow: 0 24px 70px rgba(15, 23, 42, 0.12);
            backdrop-filter: blur(16px);
        }

        .auth-title {
            margin: 0 0 10px;
            font-size: 2rem;
            line-height: 1.2;
            color: #0f172a;
        }

        .auth-subtitle {
            margin: 0 0 28px;
            color: #64748b;
            line-height: 1.8;
        }

        .field {
            margin-bottom: 18px;
        }

        .field label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.98rem;
            font-weight: 700;
            color: #334155;
        }

        .field input {
            width: 100%;
            height: 52px;
            padding: 0 16px;
            border: 1px solid #dbe4f0;
            border-radius: 16px;
            background: #fff;
            color: #0f172a;
            font: inherit;
            outline: none;
            transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
        }

        .field input::placeholder {
            color: #94a3b8;
        }

        .field input:focus {
            border-color: #d97706;
            box-shadow: 0 0 0 4px rgba(217, 119, 6, 0.14);
            transform: translateY(-1px);
        }

        .checkbox-row {
            margin: 18px 0 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .remember {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: #475569;
            font-size: 0.95rem;
        }

        .remember input {
            width: 18px;
            height: 18px;
            accent-color: #d97706;
        }

        .checkbox-row a {
            color: #d97706;
            text-decoration: none;
            font-weight: 700;
        }

        .checkbox-row a:hover {
            text-decoration: underline;
        }

        .auth-button {
            width: 100%;
            min-height: 54px;
            border: 0;
            border-radius: 16px;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 45%, #d97706 100%);
            color: #fff;
            font: inherit;
            font-size: 1.05rem;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.16);
            transition: transform 0.18s ease, box-shadow 0.18s ease, opacity 0.18s ease;
        }

        .auth-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 18px 34px rgba(15, 23, 42, 0.2);
        }

        .auth-button:active {
            transform: translateY(0);
        }

        .auth-error {
            display: block;
            margin-top: 8px;
            color: #b91c1c;
            font-size: 0.9rem;
        }

        .auth-footer {
            margin-top: 18px;
            text-align: center;
            color: #64748b;
            line-height: 1.8;
        }

        .auth-footer a {
            color: #0f172a;
            font-weight: 700;
            text-decoration: none;
        }

        .auth-footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 640px) {
            .auth-card {
                padding: 28px 20px;
                border-radius: 20px;
            }

            .checkbox-row {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-panel">
            <div class="auth-card">
                <div class="auth-header" style="text-align:center; margin-bottom: 32px;">
                    <div class="auth-logo" style="width: 60px; height: 60px; margin: 0 auto 16px; border-radius: 16px; display: grid; place-items: center; font-size: 28px; font-weight: 800; color: #0f172a; background: linear-gradient(135deg, #fbbf24, #fde68a); box-shadow: 0 16px 40px rgba(251, 191, 36, 0.3);">⚖</div>
                    <h1 style="margin: 0; font-size: 1.9rem; line-height: 1.2; color: #0f172a;">{{ \App\Models\Setting::get('system_name', 'الميّزان') }}</h1>
                    <p style="margin: 8px 0 0; color: #64748b; line-height: 1.8;">نظام إدارة المحاسبة والمبيعات</p>
                </div>
                @yield('content')
            </div>
        </section>
    </main>
</body>
</html>
