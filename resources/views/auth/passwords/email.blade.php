{{--
    The "send me the link again" page (GET /password/reset, route password.request).
    The login modal is the normal way in; this is what a bookmarked or shared
    /password/reset URL opens, and it is a guest page for the same reason
    passwords/reset.blade.php is: no dashboard chrome in front of a form that
    only ever has one field.
--}}
@extends('layouts.auth')

@section('title', 'Forgot Password - TCN Ikorodu')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="text-center mb-4">
                <h4 class="mb-1">TCN Ikorodu</h4>
                <p class="text-muted mb-0">Forgot your password?</p>
            </div>

            <div class="card" style="box-shadow: 0 6px 24px rgba(15, 23, 42, .08);">
                <div class="card-header">
                    <h6 class="mb-0">Send a reset link</h6>
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

                    <form method="POST" action="{{ route('password.email') }}">
                        @csrf

                        <div class="form-group mb-3">
                            <label for="email">Email Address</label>
                            <input id="email" type="email" class="form-control @error('email') is-invalid @enderror"
                                name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
                            <small class="form-text text-muted">
                                The address on your church account.
                            </small>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block w-100 mt-4">
                            Send Password Reset Link
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
