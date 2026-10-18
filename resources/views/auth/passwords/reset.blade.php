{{--
    The page the link in the "Forgot Password" mail opens.

    It used to extend layouts.app, so the reset form arrived wrapped in the
    signed-in dashboard: left menu, welcome name, notification bell. Somebody
    following a mail on a phone reads that as "this is the wrong page" (or as
    having been logged in), and every link in that menu leads to a login
    redirect anyway. A reset link is opened by a visitor who is *not* signed in,
    so this uses layouts.auth - the bare, logged-out shell /mregister already
    uses - with one card, and the route itself needs no login (routes/web.php).
--}}
@extends('layouts.auth')

@section('title', 'Reset Password - TCN Ikorodu')

@push('styles')
<style>
    /* The card stands alone on the empty shell, so it needs the shadow the
       dashboard's cards get from their surrounding panels. */
    .auth-card { box-shadow: 0 6px 24px rgba(15, 23, 42, .08); }
    /* Same show/hide control as the login page (auth/login.blade.php). */
    .toggle-password { cursor: pointer; }
</style>
@endpush

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="text-center mb-4">
                <h4 class="mb-1">TCN Ikorodu</h4>
                <p class="text-muted mb-0">Reset your password</p>
            </div>

            <div class="card auth-card">
                <div class="card-header">
                    <h6 class="mb-0">Choose a new password</h6>
                </div>

                <div class="card-body">
                    <p class="text-muted">
                        For <strong>{{ $email ?? old('email') }}</strong>.
                    </p>

                    @if ($errors->any())
                        <div class="alert alert-danger" role="alert">
                            @foreach ($errors->all() as $error)
                                {{ $error }}<br>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('password.update') }}">
                        @csrf

                        <input type="hidden" name="token" value="{{ $token }}">

                        <div class="form-group mb-3">
                            <label for="email">Email Address</label>
                            <input id="email" type="email" class="form-control @error('email') is-invalid @enderror"
                                name="email" value="{{ $email ?? old('email') }}" required autocomplete="email">
                            <small class="form-text text-muted">The address this reset link was sent to.</small>
                        </div>

                        <div class="form-group mb-3">
                            <label for="password">New Password</label>
                            <div class="input-group">
                                <input id="password" type="password" class="form-control @error('password') is-invalid @enderror"
                                    name="password" required minlength="8" autocomplete="new-password" autofocus>
                                <div class="input-group-append">
                                    <span class="input-group-text toggle-password" role="button" tabindex="0"
                                        title="Show password" aria-label="Show password">
                                        <i class="fas fa-eye" aria-hidden="true"></i>
                                    </span>
                                </div>
                            </div>
                            <small class="form-text text-muted">At least 8 characters.</small>
                        </div>

                        <div class="form-group mb-3">
                            <label for="password-confirm">Confirm New Password</label>
                            <div class="input-group">
                                <input id="password-confirm" type="password" class="form-control"
                                    name="password_confirmation" required minlength="8" autocomplete="new-password">
                                <div class="input-group-append">
                                    <span class="input-group-text toggle-password" role="button" tabindex="0"
                                        title="Show password" aria-label="Show password">
                                        <i class="fas fa-eye" aria-hidden="true"></i>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block w-100 mt-4">
                            Reset Password
                        </button>

                        <p class="mb-0 mt-3 text-center">
                            <a class="btn-link" href="{{ route('login') }}">Back to sign in</a>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection


@push('scripts')
<script>
    // Typing a new password twice blind is the usual reason a reset "fails" with
    // a mismatch, so both boxes get the eye the login page has.
    $(function () {
        function flip(button) {
            var $button = $(button);
            var $input = $button.closest('.input-group').find('input');
            var hidden = $input.attr('type') === 'password';

            $input.attr('type', hidden ? 'text' : 'password');
            $button.find('i').toggleClass('fa-eye fa-eye-slash');
            $button.attr('aria-label', hidden ? 'Hide password' : 'Show password');
        }

        $('.toggle-password').on('click', function () {
            flip(this);
        }).on('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                flip(this);
            }
        });
    });
</script>
@endpush
