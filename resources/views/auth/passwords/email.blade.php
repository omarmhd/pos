@extends('layouts.auth')

@section('title', 'إعادة تعيين كلمة المرور')

@section('content')
    <h2 class="auth-title">إعادة تعيين كلمة المرور</h2>
    <p class="auth-subtitle">أدخل بريدك الإلكتروني لاستقبال رابط الإعادة</p>

    @if (session('status'))
        <div class="alert alert-success" role="alert" style="margin-top: 16px; padding: 10px 12px; background:#d4edda; color:#155724; border-radius:8px; font-size:13px;">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" style="margin-top: 24px;">
        @csrf

        <div class="field">
            <label for="email">البريد الإلكتروني</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus placeholder="admin@example.com">
            @error('email')
                <span class="auth-error">{{ $message }}</span>
            @enderror
        </div>

        <button type="submit" class="auth-button">إرسال رابط الإعادة</button>
    </form>
@endsection

