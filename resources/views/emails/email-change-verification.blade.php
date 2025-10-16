@component('mail::message')
# Email Change Verification

You are changing your email address. Please use the following OTP to verify your new email address:

**OTP: {{ $otp }}**

This OTP will expire in 30 minutes.

If you didn't request this change, please contact support immediately.

Thanks,<br>
{{ config('app.name') }}
@endcomponent