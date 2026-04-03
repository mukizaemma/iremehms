<div class="min-vh-100 d-flex flex-lg-row flex-column bg-light login-split">
    <!-- Left: Form -->
    <div class="login-left d-flex flex-column justify-content-center p-4 p-md-5">
        <div class="mb-4 text-center">
            @if($iremeLogoUrl)
                <img
                    src="{{ $iremeLogoUrl }}"
                    alt="{{ $hotelName }}"
                    style="max-height: 96px; width: auto; object-fit: contain;"
                    onerror="this.style.display='none'"
                >
            @else
                <h2 class="text-primary mb-1">{{ $hotelName }}</h2>
            @endif
        </div>

        <div class="bg-white rounded shadow-sm p-4 p-sm-5">
            <h4 class="mb-4">Sign In</h4>

            @if ($error)
                <div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
                    {{ $error }}
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <form wire:submit.prevent="login">
                <div class="form-floating mb-3">
                    <input
                        type="email"
                        class="form-control @error('email') is-invalid @enderror"
                        id="floatingInput"
                        placeholder="name@example.com"
                        wire:model.blur="email"
                        wire:change="loadUserModules"
                    >
                    <label for="floatingInput">Email address</label>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-floating mb-3">
                    <input
                        type="password"
                        class="form-control @error('password') is-invalid @enderror"
                        id="floatingPassword"
                        placeholder="Password"
                        wire:model="password"
                    >
                    <label for="floatingPassword">Password</label>
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="rememberMe">
                        <label class="form-check-label small" for="rememberMe">Remember me</label>
                    </div>
                    <a href="{{ route('password.request') }}" class="small">Forgot Password?</a>
                </div>

                <button type="submit" class="btn btn-primary py-2 px-4 w-100 mb-3">
                    Sign In
                </button>

                <p class="text-center small text-muted mb-0">
                    Don't have an account? <a href="{{ route('register') }}">Sign Up</a>
                </p>
            </form>
        </div>
    </div>

    <!-- Right: Image -->
    <div class="login-right d-none d-lg-flex position-relative login-right-column">
        <div class="position-absolute top-0 start-0 w-100 h-100 login-right-gradient" aria-hidden="true"></div>
        @if($loginRightImageUrl)
            <img src="{{ $loginRightImageUrl }}" alt="" class="position-absolute top-0 start-0 w-100 h-100" style="object-fit: cover; z-index: 0;" onerror="this.style.display='none'">
        @endif
    </div>

    <style>
        .login-split { min-height: 100vh; }
        .login-left { flex: 1 1 50%; min-width: 0; }
        .login-right { flex: 1 1 50%; min-width: 0; min-height: 100vh; }
        .login-right-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            z-index: 0;
        }
        .login-right-column { min-height: 100vh; }
        @media (max-width: 991.98px) {
            .login-left { flex: 1 1 100%; min-height: 100vh; }
        }
    </style>
</div>
