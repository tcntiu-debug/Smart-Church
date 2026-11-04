TCN IKORODU SMART CHURCH - ACTIVATE YOUR ACCOUNT
=================================================

Hello {{ $firstName !== '' ? $firstName : 'there' }},

An account was created for you on the TCN Ikorodu Smart Church app.
Confirm your e-mail address with the link below to activate it:

{{ $verifyUrl }}

The link works for {{ $expiresInDays }} days and can only be used once.
Already activated? Sign in here: {{ route('login') }}

If you were not expecting this e-mail, you can ignore it - the account stays
inactive until the link above is opened.

No password is in this e-mail: the link above lets you choose one, then takes
you to the login page.

--
Sent automatically by Smart-Church.
