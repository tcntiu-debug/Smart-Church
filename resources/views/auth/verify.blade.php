{{--
    The "activate your account" page (GET /member/verification/notice, route
    member.verification.notice).

    It has to be a guest page: an unverified member cannot sign in
    (MemberSessionService::canSignIn()), which is exactly who needs this form.
    It uses layouts.auth - the bare, logged-out shell the reset form uses - with
    one field, and posts to the route the activation mail's resend button uses.
--}}
@extends('layouts.auth')

@section('title', 'Activate Your Account - TCN Ikorodu')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="text-center mb-4">
                <h4 class="mb-1">TCN Ikorodu</h4>
                <p class="text-muted mb-0">Activate your account</p>
            </div>

            <div class="card" style="box-shadow: 0 6px 24px rgba(15, 23, 42, .08);">
                <div class="card-header">
                    <h6 class="mb-0">Send a fresh activation link</h6>
                </div>

                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger" role="alert">
                            @foreach ($errors->all() as $error)
                                {{ $error }}<br>
                            @endforeach
                        </div>
                    @endif

                    <p class="text-muted" style="font-size:13px;">
                        The account already exists; it only needs the link in the
                        activation e-mail opened once. Enter the address it was created
                        with and we will send a new link (valid for
                        {{ \App\Services\MemberVerificationService::LINK_DAYS }} days).
                    </p>

                    <form method="POST" action="{{ route('member.verification.resend') }}">
                        @csrf

                        <div class="form-group mb-3">
                            <label for="email">Email Address</label>
                            <input id="email" type="email" class="form-control @error('email') is-invalid @enderror"
                                name="email" value="{{ old('email', request('email')) }}" required autocomplete="email" autofocus>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block w-100 mt-4">
                            Send Activation Link
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

