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
                radial-gradient(circle at 18% 18%, rgba(251, 191, 36, 0.22), transparent 0 18%, transparent 19%),
                radial-gradient(circle at 82% 20%, rgba(59, 130, 246, 0.16), transparent 0 16%, transparent 17%),
                radial-gradient(circle at 20% 80%, rgba(15, 23, 42, 0.10), transparent 0 18%, transparent 19%),
                radial-gradient(circle at 78% 78%, rgba(217, 119, 6, 0.18), transparent 0 20%, transparent 21%),
                linear-gradient(135deg, #dbeafe 0%, #eef2ff 35%, #f8fafc 100%);
            position: relative;
            overflow: hidden;
        }

        body.auth-page::before,
        body.auth-page::after {
            content: '';
            position: fixed;
            inset: auto;
            border-radius: 999px;
            pointer-events: none;
            filter: blur(12px);
            opacity: 0.75;
        }

        body.auth-page::before {
            width: 320px;
            height: 320px;
            top: -90px;
            right: -80px;
            background: radial-gradient(circle, rgba(251, 191, 36, 0.35) 0%, rgba(251, 191, 36, 0.08) 55%, transparent 72%);
        }

        body.auth-page::after {
            width: 260px;
            height: 260px;
            bottom: -80px;
            left: -70px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.20) 0%, rgba(99, 102, 241, 0.06) 58%, transparent 74%);
        }

        .auth-shell {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 16px;
            position: relative;
            z-index: 1;
        }

        .auth-panel {
            width: 100%;
            display: grid;
            place-items: center;
        }

        .auth-card {
            width: 100%;
            max-width: 600px;
            max-height: calc(100vh - 32px);
            overflow-y: auto;
            padding: 32px 32px 28px;
            border-radius: 22px;
            background: rgba(255, 255, 255, 0.88);
            border: 1px solid rgba(148, 163, 184, 0.18);
            box-shadow: 0 28px 80px rgba(15, 23, 42, 0.14);
            backdrop-filter: blur(18px);
        }

        .auth-title {
            margin: 0 0 10px;
            font-size: 2rem;
            line-height: 1.2;
            color: #0f172a;
        }

        .auth-subtitle {
            margin: 0 0 28px;
                    * { margin: 0; padding: 0; box-sizing: border-box; }
                    body {
                        font-family: 'Cairo', sans-serif;
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        min-height: 100vh;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }
                    .auth-shell {
                        width: 100%;
                        max-width: 420px;
                        padding: 20px;
                    }
                    .auth-card {
                        background: white;
                        border-radius: 16px;
                        box-shadow: 0 20px 60px rgba(0,0,0,0.15);
                        padding: 40px 30px;
                    }
                    .auth-header {
                        text-align: center;
                        margin-bottom: 32px;
                    }
                    .auth-logo {
                        width: 60px;
                        height: 60px;
                        margin: 0 auto 16px;
                        background: linear-gradient(135deg, #667eea, #764ba2);
                        color: white;
                        border-radius: 12px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-size: 28px;
                        font-weight: 800;
                    }
                    .auth-title {
                        font-size: 24px;
                        font-weight: 700;
                        color: #1a1a1a;
                        margin-bottom: 8px;
                    }
                    .auth-subtitle {
                        font-size: 13px;
                        color: #888;
                        line-height: 1.6;
                    }
                    .field {
                        margin-bottom: 18px;
                    }
                    .field label {
                        display: block;
                        font-size: 13px;
                        font-weight: 600;
                        color: #333;
                        margin-bottom: 6px;
                    }
                    .field input {
                        width: 100%;
                        padding: 10px 12px;
                        border: 1px solid #e0e0e0;
                        border-radius: 8px;
                        font-size: 14px;
                        font-family: inherit;
                        transition: all 0.2s;
                    }
                    .field input:focus {
                        outline: none;
                        border-color: #667eea;
                        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
                    }
                    .checkbox-row {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        margin-bottom: 22px;
                        font-size: 13px;
                    }
                    .remember {
                        display: flex;
                        align-items: center;
                        gap: 6px;
                        cursor: pointer;
                        color: #666;
                    }
                    .remember input {
                        cursor: pointer;
                    }
                    .checkbox-row a {
                        color: #667eea;
                        text-decoration: none;
                        font-weight: 500;
                    }
                    .checkbox-row a:hover {
                        text-decoration: underline;
                    }
                    .auth-button {
                        width: 100%;
                        padding: 11px;
                        background: linear-gradient(135deg, #667eea, #764ba2);
                        color: white;
                        border: none;
                        border-radius: 8px;
                        font-size: 14px;
                        font-weight: 600;
                        cursor: pointer;
                        transition: all 0.3s;
                        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
                    }
                    .auth-button:hover {
                        transform: translateY(-2px);
                        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
                    }
                    .auth-button:active {
                        transform: translateY(0);
                    }
                    .auth-error {
                        display: block;
                        color: #e74c3c;
                        font-size: 12px;
                        margin-top: 4px;
                    }
                    .auth-footer {
                        text-align: center;
                        margin-top: 20px;
                        font-size: 13px;
                        color: #888;
                    }
                    .auth-footer a {
                        color: #667eea;
                        text-decoration: none;
                        font-weight: 600;
                    }
                    .auth-footer a:hover {
                        text-decoration: underline;
                    }
            color: #fff;
            font: inherit;
            <body>
            font-weight: 800;
                    <section class="auth-card">
                        <div class="auth-header">
                            <div class="auth-logo">⚖</div>
                            <h1 class="auth-title">{{ \App\Models\Setting::get('system_name', 'الميّزان') }}</h1>
                            <p class="auth-subtitle">نظام إدارة المحاسبة والمبيعات</p>
                        </div>
                        @yield('content')
                    </section>
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
            .auth-shell {
                padding: 10px;
            }

            .auth-card {
                max-height: calc(100vh - 20px);
                padding: 24px 18px 22px;
                border-radius: 18px;
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
