<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Judging System for Competition')</title>
    <meta name="description" content="Judging System for Competition">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <meta name="theme-color" content="#0b1f4d">
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/evaluate.js'])
    <script>
        // Back/Forward fix: if a browser still restores this page from the
        // back-forward cache, reload it so the server shows the current state.
        // (A reloaded page is never "persisted", so this cannot loop.)
        window.addEventListener('pageshow', function (e) {
            if (e.persisted) { window.location.reload(); }
        });
    </script>
    @yield('head')
</head>
<body class="app-body min-h-screen bg-pale text-navy-900 antialiased">
    @auth
        <header class="app-header">
            <div class="app-header__inner">
                <a href="{{ route('home') }}" class="app-brand" aria-label="Judging System for Competition">
                    <img src="{{ asset('images/logo.svg') }}" alt="Judging System for Competition logo" class="app-brand__mark" width="38" height="38">
                    <span class="app-brand__text">
                        <span class="app-brand__title">Judging System for Competition</span>
                        <span class="app-brand__subtitle">Innovation Challenge 2026</span>
                    </span>
                </a>

                <nav class="app-nav hidden lg:flex" aria-label="Main navigation">
                    @if (auth()->user()->isAdmin())
                        @php
                            $nav = [
                                ['route' => 'admin.dashboard', 'label' => 'Dashboard', 'pattern' => 'admin.dashboard'],
                                ['route' => 'admin.finalists.index', 'label' => 'Finalists', 'pattern' => 'admin.finalists.*'],
                                ['route' => 'admin.presentations.index', 'label' => 'Presentations', 'pattern' => 'admin.presentations.*'],
                                ['route' => 'admin.criteria.index', 'label' => 'Rubric', 'pattern' => 'admin.criteria.*'],
                                ['route' => 'admin.judges.index', 'label' => 'Judges', 'pattern' => 'admin.judges.*'],
                                ['route' => 'admin.evaluations.index', 'label' => 'Evaluations', 'pattern' => 'admin.evaluations.*'],
                                ['route' => 'admin.results.index', 'label' => 'Results', 'pattern' => 'admin.results.*'],
                                ['route' => 'admin.voting.index', 'label' => 'Voting', 'pattern' => 'admin.voting.*'],
                                ['route' => 'admin.settings.index', 'label' => 'Settings', 'pattern' => 'admin.settings.*'],
                            ];
                        @endphp
                        @foreach ($nav as $item)
                            <a href="{{ route($item['route']) }}"
                               class="app-nav__link {{ request()->routeIs($item['pattern']) ? 'is-active' : '' }}">
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    @else
                        <a href="{{ route('judge.dashboard') }}"
                           class="app-nav__link {{ request()->routeIs('judge.dashboard') ? 'is-active' : '' }}">
                            My Judging
                        </a>
                    @endif
                </nav>

                <div class="app-header__user">
                    <div class="app-user__meta hidden sm:block">
                        <p class="app-user__name">{{ auth()->user()->name }}</p>
                        <p class="app-user__role">{{ auth()->user()->isAdmin() ? 'Administrator' : 'Judge' }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="app-btn app-btn--outline">
                            Sign out
                        </button>
                    </form>
                </div>
            </div>

            @if (auth()->user()->isAdmin())
                <nav class="app-mobile-nav lg:hidden" aria-label="Mobile navigation">
                    <div class="app-mobile-nav__list">
                        @php
                            $nav = [
                                ['route' => 'admin.dashboard', 'label' => 'Dashboard', 'pattern' => 'admin.dashboard'],
                                ['route' => 'admin.finalists.index', 'label' => 'Finalists', 'pattern' => 'admin.finalists.*'],
                                ['route' => 'admin.presentations.index', 'label' => 'Presentations', 'pattern' => 'admin.presentations.*'],
                                ['route' => 'admin.criteria.index', 'label' => 'Rubric', 'pattern' => 'admin.criteria.*'],
                                ['route' => 'admin.judges.index', 'label' => 'Judges', 'pattern' => 'admin.judges.*'],
                                ['route' => 'admin.evaluations.index', 'label' => 'Evaluations', 'pattern' => 'admin.evaluations.*'],
                                ['route' => 'admin.results.index', 'label' => 'Results', 'pattern' => 'admin.results.*'],
                                ['route' => 'admin.voting.index', 'label' => 'Voting', 'pattern' => 'admin.voting.*'],
                                ['route' => 'admin.settings.index', 'label' => 'Settings', 'pattern' => 'admin.settings.*'],
                            ];
                        @endphp
                        @foreach ($nav as $item)
                            <a href="{{ route($item['route']) }}"
                               class="app-mobile-nav__link {{ request()->routeIs($item['pattern']) ? 'is-active' : '' }}">
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </div>
                </nav>
            @endif
        </header>
    @endauth

    <main class="app-page">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
                {{ session('status') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
                <p class="font-semibold">Please check the following:</p>
                <ul class="mt-1 list-disc space-y-0.5 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="app-footer">
        <div class="app-footer__inner">
            <p class="text-xs text-slate-500">&copy; 2026 Judging System for Competition &middot; Innovation Challenge 2026 &middot; Demo Company</p>
            <p class="text-xs font-medium text-gold-700">Demo environment &middot; synthetic data only</p>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
