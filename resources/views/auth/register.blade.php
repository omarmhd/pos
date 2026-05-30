@extends('layouts.auth')

@section('title', 'إنشاء حساب')

@section('content')
    <h2 class="auth-title">إنشاء حساب جديد</h2>
    <p class="auth-subtitle">أضف مستخدم جديد للنظام</p>

    <form method="POST" action="{{ route('register') }}" style="margin-top: 24px;">
        @csrf

        <div class="field">
            <label for="name">الاسم</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" placeholder="أدخل الاسم الكامل">
            @error('name')
                <span class="auth-error">{{ $message }}</span>
            @enderror
        </div>

        <div class="field">
            <label for="email">البريد الإلكتروني</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="user@example.com">
            @error('email')
                <span class="auth-error">{{ $message }}</span>
            @enderror
        </div>

        <div class="field">
            <label for="password">كلمة المرور</label>
            <input id="password" type="password" name="password" required autocomplete="new-password" placeholder="••••••••">
            @error('password')
                <span class="auth-error">{{ $message }}</span>
            @enderror
        </div>

        <div class="field">
            <label for="password-confirm">تأكيد كلمة المرور</label>
            <input id="password-confirm" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="••••••••">
        </div>

        <button type="submit" class="auth-button">إنشاء الحساب</button>

        <div class="auth-footer">
            <span>لديك حساب بالفعل؟</span>
            <a href="{{ route('login') }}">تسجيل الدخول</a>
        </div>
    </form>
@endsection

