@extends('layouts.auth')

@section('title', 'تأكيد البريد الإلكتروني')

@section('content')
    <h2 class="auth-title">تأكيد البريد الإلكتروني</h2>
    <p class="auth-subtitle">قبل المتابعة، تحقق من بريدك الإلكتروني واتبع رابط التفعيل.</p>

    @if (session('resent'))
        <div class="alert alert-success" role="alert">
            {{ __('A fresh verification link has been sent to your email address.') }}
        </div>
    @endif

    <div class="auth-footer text-start">
        <span>{{ __('If you did not receive the email') }}،</span>
        <form class="d-inline" method="POST" action="{{ route('verification.resend') }}">
            @csrf
            <button type="submit" class="btn btn-link p-0 align-baseline">{{ __('click here to request another') }}</button>.
        </form>
    </div>
@endsection
