@extends('layouts.auth')

@section('title', 'تعيين كلمة مرور جديدة')

@section('content')
    <h2 class="auth-title">تعيين كلمة مرور جديدة</h2>
    <p class="auth-subtitle">أدخل البريد وكلمة المرور الجديدة</p>

    <form method="POST" action="{{ route('password.update') }}" style="margin-top: 24px;">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <div class="field">
            <label for="email">البريد الإلكتروني</label>
            <input id="email" type="email" name="email" value="{{ $email ?? old('email') }}" required autocomplete="email" autofocus placeholder="admin@example.com">
            @error('email')
                <span class="auth-error">{{ $message }}</span>
            @enderror
        </div>

        <div class="field">
            <label for="password">كلمة المرور الجديدة</label>
            <input id="password" type="password" name="password" required autocomplete="new-password" placeholder="••••••••">
            @error('password')
                <span class="auth-error">{{ $message }}</span>
            @enderror
        </div>

        <div class="field">
            <label for="password-confirm">تأكيد كلمة المرور</label>
            <input id="password-confirm" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="••••••••">
        </div>

        <button type="submit" class="auth-button">تعيين كلمة المرور</button>
    </form>
@endsection

