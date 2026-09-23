<x-mail::message>
# Welcome, {{ $user->name }}

An administrator created your Employee Timekeeping &amp; Workforce Management System account.
Use the secure link below to choose your password. This link expires and can only be used once.

<x-mail::button :url="$activationUrl">
Set up my account
</x-mail::button>

If you were not expecting this invitation, contact your administrator.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
