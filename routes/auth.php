<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\PhoneAuthController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    // Phone + Telegram OTP — the primary auth path.
    Route::get('login', [PhoneAuthController::class, 'showLogin'])->name('login');
    Route::post('login/otp/request', [PhoneAuthController::class, 'requestLoginOtp'])->name('login.otp.request');
    Route::post('login/otp/verify', [PhoneAuthController::class, 'verifyLoginOtp'])->name('login.otp.verify');

    Route::get('register', [PhoneAuthController::class, 'showRegister'])->name('register');
    Route::post('register/otp/request', [PhoneAuthController::class, 'requestRegisterOtp'])->name('register.otp.request');
    Route::post('register/otp/verify', [PhoneAuthController::class, 'verifyRegisterOtp'])->name('register.otp.verify');

    // Email + password fallback for legacy accounts (during the migration
    // period). Will be retired once all clients have a phone-linked Telegram.
    Route::get('login/email', [AuthenticatedSessionController::class, 'create'])->name('login.email');
    Route::post('login/email', [AuthenticatedSessionController::class, 'store'])->name('login.email.store');
    Route::get('register/email', [RegisteredUserController::class, 'create'])->name('register.email');
    Route::post('register/email', [RegisteredUserController::class, 'store'])->name('register.email.store');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
