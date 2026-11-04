{{--
    The page the activation link lands on (route member.password.create).

    Public registration stores a random password nobody is ever told, so once
    the address is verified the member chooses their own here and saving it
    sends them to the login page. Guests only - an unverified account cannot
    sign in - and open only for the member the link just verified in this
    browser (MemberController::verificationMember()).
--}}
@extends('layouts.auth')

@section('title', 'Choose Your Password - TCN Ikorodu')

@push('styles')
<style>
    .auth-card { box-shadow: 0 6px 24px rgba(15, 23, 42, .08); }
    .toggle-password { cursor: pointer; }
</style>
@endpush

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="text-center mb-4">
                <h4 class="mb-1">TCN Ikorodu</h4>
                <p class="text-muted mb-0">Your e-mail address is verified</p>
            </div>

            <div class="card auth-card">
                <div class="card-header">
                    <h6 class="mb-0">Choose a password</h6>
                </div>

                <div class="card-body">
                    @if (session('success'))
                        <div class="alert alert-success" role="alert">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger" role="alert">
                            @foreach ($errors->all() as $error)
                                {{ $error }}<br>
                            @endforeach
                        </div>
                    @endif

                    <p class="text-muted">
                        For <strong>{{ $email }}</strong>.
                    </p>

                    <form method="POST" action="{{ route('member.password.store') }}">
                        @csrf

                        <div class="form-group mb-3">
                            <label for="password">Password</label>
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
                            <label for="password-confirm">Confirm Password</label>
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
                            Save Password &amp; Continue
                        </button>

                        <p class="mb-0 mt-3 text-center">
                            <a class="btn-link" href="{{ route('login') }}">Skip for now - go to sign in</a>
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
    // Typing a new password twice blind is the usual reason a form "fails" with
    // a mismatch, so both boxes get the eye the reset form has.
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
