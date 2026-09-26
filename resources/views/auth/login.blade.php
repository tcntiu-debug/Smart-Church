<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>TIU Dashboard - Login</title>
    <!-- Iconic Fonts -->
    <link href="{{ asset('vendors/iconic-fonts/flat-icons/flaticon.css') }}" rel="stylesheet">
    <link href="{{ asset('vendors/iconic-fonts/font-awesome/css/all.min.css') }}" rel="stylesheet">
    <!-- Bootstrap core CSS -->
    <link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/jquery-ui.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/style.css') }}" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.ico') }}">

    <style>
        #togglePassword { cursor: pointer; }
        .alert-custom {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .alert-success-custom {
            background-color: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
    </style>
</head>

<body class="ms-body ms-primary-theme ms-logged-out">

    <main class="body-content">
        <div class="ms-content-wrapper ms-auth">
            <div class="ms-auth-container">
                <div class="ms-auth-col">
                    <div class="ms-auth-bg"></div>
                </div>
                <div class="ms-auth-col">
                    <div class="ms-auth-form">
                        <form method="POST" action="{{ route('login') }}">
                            @csrf
                            <h3>Login to Account</h3>
                            
                            @if($errors->any())
                                <div class="alert-custom">
                                    @foreach($errors->all() as $error)
                                        {{ $error }}<br>
                                    @endforeach
                                </div>
                            @endif

                            @if(session('error'))
                                <div class="alert-custom">{{ session('error') }}</div>
                            @endif

                            @if(session('success'))
                                <div class="alert-success-custom">{{ session('success') }}</div>
                            @endif

                            <div class="mb-3">
                                <label for="email">Email Address</label>
                                <div class="input-group">
                                    <input type="email" name="email" class="form-control" id="email"
                                        placeholder="Email Address" value="{{ old('email') }}" required autofocus>
                                </div>
                            </div>

                            <div class="mb-2">
                                <label for="password">Password</label>
                                <div class="input-group">
                                    <input type="password" name="password" class="form-control" id="password"
                                        placeholder="Password" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text" id="togglePassword">
                                            <i class="fas fa-eye" aria-hidden="true"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="d-block mt-3">
                                    <a href="#" class="btn-link" data-toggle="modal" data-target="#modal-12">Forgot Password?</a>
                                </label>
                            </div>

                            <button type="submit" class="btn btn-primary mt-4 d-block w-100">
                                Sign In
                            </button>

                            <p class="mb-0 mt-3 text-center">
                                <a class="btn-link" href="{{ url('/privacy-policy') }}">Privacy Policy</a>
                            </p>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Forgot Password Modal -->
        <div class="modal fade" id="modal-12" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-dialog-centered modal-min" role="document">
                <div class="modal-content">
                    <div class="modal-body text-center">
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                        <i class="flaticon-secure-shield d-block"></i>
                        <h1>Forgot Password?</h1>
                        <p>Enter your email to recover your password</p>
                        <div id="forgot-message"></div>
                        <div class="ms-form-group has-icon">
                            <input type="text" id="emailReset" placeholder="Email Address" class="form-control">
                            <i class="material-icons">email</i>
                        </div>
                        <button onclick="passwordReset()" type="button" class="btn btn-primary shadow-none">
                            Reset Password
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="{{ asset('assets/js/jquery-3.3.1.min.js') }}"></script>
    <script src="{{ asset('assets/js/popper.min.js') }}"></script>
    <script src="{{ asset('assets/js/bootstrap.min.js') }}"></script>
    <script src="{{ asset('assets/js/framework.js') }}"></script>
    
    <script>
        // Toggle password visibility
        $(document).ready(function() {
            $("#togglePassword").on('click', function(e) {
                e.preventDefault();
                const password = $("#password");
                const type = password.attr("type") === "password" ? "text" : "password";
                password.attr("type", type);
                $(this).find("i").toggleClass("fa-eye fa-eye-slash");
            });
        });

        function passwordReset() {
            var email = $("#emailReset").val();
            var messageDiv = $("#forgot-message");
            
            if (!email) {
                messageDiv.html('<div class="alert-custom">Please enter your email address</div>');
                return;
            }
            
            $.ajax({
                type: "POST",
                url: "{{ route('password.email') }}",
                data: { email: email, _token: "{{ csrf_token() }}" },
                success: function(data) {
                    messageDiv.html('<div class="alert-success-custom">Password reset link sent to your email!</div>');
                },
                error: function(xhr) {
                    messageDiv.html('<div class="alert-custom">' + xhr.responseJSON?.email?.[0] || 'Email not found' + '</div>');
                }
            });
        }
    </script>
</body>
</html>