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
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100dvh;
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
    </style>
</head>
<body>
    <main class="auth-shell">
        <section class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">⚖</div>
                <h1 class="auth-title">{{ \App\Models\Setting::get('system_name', 'الميّزان') }}</h1>
                <p class="auth-subtitle">نظام إدارة المحاسبة والمبيعات</p>
            </div>
            @yield('content')
        </section>
    </main>
</body>
</html>
