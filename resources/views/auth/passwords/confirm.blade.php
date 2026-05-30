@extends('layouts.auth')

@section('title', 'تأكيد كلمة المرور')

@section('content')
    <h2 class="auth-title">تأكيد كلمة المرور</h2>
    <p class="auth-subtitle">يرجى إدخال كلمة المرور قبل متابعة هذا الإجراء الحساس.</p>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <div class="field">
            <label for="password">{{ __('Password') }}</label>
            <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
            @error('password')
                <span class="auth-error">{{ $message }}</span>
            @enderror
        </div>

        <button type="submit" class="auth-button">{{ __('Confirm Password') }}</button>

        @if (Route::has('password.request'))
            <div class="auth-footer">
                <a href="{{ route('password.request') }}">{{ __('Forgot Your Password?') }}</a>
            </div>
        @endif
    </form>
@endsection
