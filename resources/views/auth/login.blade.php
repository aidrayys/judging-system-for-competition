<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Judging System for Competition - Sign in">
    <title>Judging System for Competition &middot; Sign in</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <meta name="theme-color" content="#0b1f4d">
    @vite(['resources/css/app.css', 'resources/css/login.css', 'resources/js/app.js', 'resources/js/login.js'])
    <script>window.addEventListener('pageshow', function (e) { if (e.persisted) { window.location.reload(); } });</script>
</head>
<body class="jc-login">
    <div class="jc-shell">
        {{-- ===================== LEFT: BRANDING & ILLUSTRATION ===================== --}}
        <section class="jc-brand" aria-label="Judging System for Competition branding">
            <div class="jc-brand__decor jc-brand__decor--top" aria-hidden="true"></div>
            <div class="jc-brand__decor jc-brand__decor--bottom" aria-hidden="true"></div>

            <div class="jc-brand__inner">
                {{-- Logos --}}
                <header class="jc-logos">
                    <img src="{{ asset('images/wordmark.svg') }}" alt="Judging System for Competition" class="jc-logos__product" width="300" height="58">
                </header>

                {{-- Headline --}}
                <div class="jc-brand__intro">
                    <h1 class="jc-title">Judging System for Competition</h1>
                    <p class="jc-script">Innovation Challenge 2026</p>
                    <p class="jc-tagline">
                        Evaluate finalists with a transparent weighted rubric, rank the results and let everyone vote for their favorite idea.
                    </p>
                </div>

                {{-- Feature highlights --}}
                <ul class="jc-features">
                    <li class="jc-feature">
                        <span class="jc-feature__icon jc-feature__icon--gold" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-3.6 10.8c.6.5.9 1.1 1 1.7h5.2c.1-.6.4-1.2 1-1.7A6 6 0 0 0 12 3Z"/>
                            </svg>
                        </span>
                        <div class="jc-feature__text">
                            <p class="jc-feature__title">Evaluate Innovation</p>
                            <p class="jc-feature__desc">Support ideas that create real impact.</p>
                        </div>
                    </li>
                    <li class="jc-feature">
                        <span class="jc-feature__icon jc-feature__icon--blue" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M16 19v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 17.5V19"/>
                                <circle cx="10" cy="7" r="3.2"/>
                                <path d="M19.5 19v-1.5a3.5 3.5 0 0 0-2.6-3.4M15.6 4.2a3.2 3.2 0 0 1 0 5.9"/>
                            </svg>
                        </span>
                        <div class="jc-feature__text">
                            <p class="jc-feature__title">Encourage Collaboration</p>
                            <p class="jc-feature__desc">Together for continuous improvement.</p>
                        </div>
                    </li>
                    <li class="jc-feature">
                        <span class="jc-feature__icon jc-feature__icon--navy" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 17l5.5-5.5 3.5 3.5L21 6"/>
                                <path d="M15 6h6v6"/>
                            </svg>
                        </span>
                        <div class="jc-feature__text">
                            <p class="jc-feature__title">Drive Positive Change</p>
                            <p class="jc-feature__desc">Turn ideas into implementation.</p>
                        </div>
                    </li>
                </ul>

                {{-- Illustration --}}
                <div class="jc-brand__art">
                    @include('partials.login-illustration')
                </div>

                {{-- Footer --}}
                <footer class="jc-footnote">
                    <span class="jc-footnote__copy">&copy; 2026 Judging System for Competition &middot; Demo Company</span>
                    <span class="jc-footnote__separator" aria-hidden="true">|</span>
                    <span class="jc-footnote__confidential">Demo environment</span>
                </footer>
            </div>
        </section>

        {{-- ===================== RIGHT: LOGIN ===================== --}}
        <section class="jc-panel" aria-label="Sign in">
            <div class="jc-panel__glow" aria-hidden="true"></div>

            <div class="jc-card">
                <div class="jc-card__logo">
                    <img src="{{ asset('images/logo.svg') }}" alt="Judging System for Competition logo" width="64" height="64">
                </div>

                <h2 class="jc-card__title">Welcome</h2>
                <p class="jc-card__subtitle">Sign in with your username and password, or use the demo accounts below.</p>

                @if (session('status'))
                    <div class="jc-alert jc-alert--ok" role="status">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="jc-alert jc-alert--error" role="alert">
                        <p class="jc-alert__title">Please check the following:</p>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="jc-form" novalidate>
                    @csrf

                    <div class="jc-field">
                        <label for="username" class="jc-label">Username</label>
                        <div class="jc-input">
                            <span class="jc-input__icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="8" r="3.6"/>
                                    <path d="M4.5 20a7.5 7.5 0 0 1 15 0"/>
                                </svg>
                            </span>
                            <input id="username" name="username" type="text" required autocomplete="username"
                                   value="{{ old('username') }}" placeholder="Enter your username"
                                   class="jc-input__field @error('username') jc-input__field--error @enderror">
                        </div>
                        @error('username')
                            <p class="jc-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="jc-field">
                        <label for="password" class="jc-label">Password</label>
                        <div class="jc-input">
                            <span class="jc-input__icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="4.5" y="10.5" width="15" height="9.5" rx="2.5"/>
                                    <path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/>
                                </svg>
                            </span>
                            <input id="password" name="password" type="password" required autocomplete="current-password"
                                   placeholder="Enter your password"
                                   class="jc-input__field @error('password') jc-input__field--error @enderror">
                            <button type="button" id="togglePassword" class="jc-input__toggle"
                                    aria-label="Show password" aria-pressed="false" title="Show password">
                                <svg class="jc-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/>
                                    <circle cx="12" cy="12" r="2.8"/>
                                </svg>
                                <svg class="jc-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 4l16 16"/>
                                    <path d="M9.6 5.9A9.6 9.6 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a17.6 17.6 0 0 1-3.2 4"/>
                                    <path d="M6.4 8.1A17.6 17.6 0 0 0 2.5 12S6 18.5 12 18.5c1.4 0 2.7-.3 3.8-.9"/>
                                    <path d="M10.2 10.4a2.8 2.8 0 0 0 3.6 3.8"/>
                                </svg>
                            </button>
                        </div>
                        @error('password')
                            <p class="jc-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="jc-remember">
                        <input id="remember" name="remember" type="checkbox" value="1" class="jc-checkbox">
                        <label for="remember">Remember me on this device</label>
                    </div>

                    <button type="submit" id="signInButton" class="jc-submit">
                        <span class="jc-submit__label">Sign in</span>
                        <span class="jc-submit__spinner" aria-hidden="true"></span>
                        <svg class="jc-submit__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                            <path d="M10 17l5-5-5-5M15 12H3"/>
                        </svg>
                    </button>
                </form>

                @if (config('judging.demo_access'))
                    @php
                        $demoAdmin = config('judging.demo_accounts.admin');
                        $demoJudges = config('judging.demo_accounts.judges');
                        $firstJudge = $demoJudges['accounts'][0] ?? null;
                    @endphp
                    <section class="jc-demo" aria-labelledby="demoTitle">
                        <h3 class="jc-demo__title" id="demoTitle">Demo Access</h3>

                        <div class="jc-demo__grid">
                            <div class="jc-demo__card">
                                <p class="jc-demo__role">Administrator</p>
                                <div class="jc-demo__row">
                                    <span class="jc-demo__label">Username</span>
                                    <code class="jc-demo__value" id="demoAdminUser">{{ $demoAdmin['username'] }}</code>
                                    <button type="button" class="jc-demo__copy" data-copy-target="demoAdminUser" aria-label="Copy administrator username">Copy</button>
                                </div>
                                <div class="jc-demo__row">
                                    <span class="jc-demo__label">Password</span>
                                    <code class="jc-demo__value" id="demoAdminPass">{{ $demoAdmin['password'] }}</code>
                                    <button type="button" class="jc-demo__copy" data-copy-target="demoAdminPass" aria-label="Copy administrator password">Copy</button>
                                </div>
                            </div>

                            @if ($firstJudge)
                                <div class="jc-demo__card">
                                    <div class="jc-demo__roleline">
                                        <p class="jc-demo__role">Judge</p>
                                        <select class="jc-demo__select" id="demoJudgeSelect" aria-label="Choose a demo judge account">
                                            @foreach ($demoJudges['accounts'] as $judge)
                                                <option value="{{ $judge['username'] }}">{{ $judge['name'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="jc-demo__row">
                                        <span class="jc-demo__label">Username</span>
                                        <code class="jc-demo__value" id="demoJudgeUser">{{ $firstJudge['username'] }}</code>
                                        <button type="button" class="jc-demo__copy" data-copy-target="demoJudgeUser" aria-label="Copy judge username">Copy</button>
                                    </div>
                                    <div class="jc-demo__row">
                                        <span class="jc-demo__label">Password</span>
                                        <code class="jc-demo__value" id="demoJudgePass">{{ $demoJudges['password'] }}</code>
                                        <button type="button" class="jc-demo__copy" data-copy-target="demoJudgePass" aria-label="Copy judge password">Copy</button>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <p class="jc-demo__note">Demo accounts are provided for exploration purposes.</p>
                    </section>
                @endif
            </div>
        </section>
    </div>
</body>
</html>
