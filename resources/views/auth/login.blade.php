@extends('layouts.auth')

@section('title', 'تسجيل الدخول')

@section('content')
    <h2 class="auth-title">تسجيل الدخول</h2>
    <p class="auth-subtitle">أدخل بيانات حسابك للمتابعة</p>

    <form method="POST" action="{{ route('login') }}" style="margin-top: 24px;">
        @csrf

        <div class="field">
            <label for="email">البريد الإلكتروني</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="admin@example.com">
            @error('email')
                <span class="auth-error">{{ $message }}</span>
            @enderror
        </div>

        <div class="field">
            <label for="password">كلمة المرور</label>
            <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
            @error('password')
                <span class="auth-error">{{ $message }}</span>
            @enderror
        </div>

        <div class="checkbox-row">
            <label class="remember" for="remember_me">
                <input id="remember_me" type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>
                <span>تذكر بيانات الدخول</span>
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}">هل نسيت كلمة المرور؟</a>
            @endif
        </div>

        <button type="submit" class="auth-button">دخول</button>
    </form>
@endsection



