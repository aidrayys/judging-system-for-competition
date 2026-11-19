<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Judging System for Competition</title>
    <meta name="description" content="Judging System for Competition. Innovation Challenge 2026.">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <meta name="theme-color" content="#0b1f4d">
    <style>
        :root { --navy:#0b1f4d; --blue:#2563eb; --yellow:#facc15; --pale:#f4f6fa; --muted:#5b6b86; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:'Plus Jakarta Sans','Segoe UI',system-ui,Arial,sans-serif; background:radial-gradient(1200px 500px at 50% -10%, #dbe7ff 0%, var(--pale) 60%); color:var(--navy); min-height:100vh; }
        .wrap { max-width:960px; margin:0 auto; padding:56px 20px 32px; text-align:center; }
        .logo { height:58px; width:auto; margin-bottom:28px; }
        .pill { display:inline-block; padding:6px 14px; border-radius:999px; background:#fff; border:1px solid #dbe4f3; color:var(--blue); font-size:.8rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; }
        h1 { font-size:clamp(2.2rem,5vw,3.4rem); margin:18px 0 8px; letter-spacing:-.03em; }
        h1 span { color:var(--blue); }
        .tagline { margin:0 auto 40px; max-width:560px; color:var(--muted); font-size:1.05rem; line-height:1.6; }
        .cards { display:grid; grid-template-columns:1fr 1fr; gap:20px; text-align:left; }
        @media (max-width:640px){ .cards { grid-template-columns:1fr; } }
        .card { background:#fff; border-radius:18px; padding:28px; border:1px solid #e1e8f4; box-shadow:0 18px 40px -22px rgba(7,20,58,.35); border-top:4px solid var(--yellow); }
        .card h2 { margin:0 0 8px; font-size:1.2rem; }
        .card p { margin:0; color:var(--muted); line-height:1.55; }
        .btn { display:inline-block; margin-top:20px; padding:12px 22px; background:var(--navy); color:#fff; border-radius:10px; text-decoration:none; font-weight:700; transition:background .15s; }
        .btn:hover { background:var(--blue); }
        .btn:focus-visible { outline:3px solid var(--yellow); outline-offset:2px; }
        footer { margin-top:40px; font-size:.8rem; color:var(--muted); }
    </style>
    <script>window.addEventListener('pageshow', function (e) { if (e.persisted) { window.location.reload(); } });</script>
</head>
<body>
<div class="wrap">
    <img class="logo" src="{{ asset('images/wordmark.svg') }}" alt="Judging System for Competition" width="300" height="58">
    <div><span class="pill">Innovation Challenge 2026</span></div>
    <h1>Judging System <span>for Competition</span></h1>
    <p class="tagline">Innovation Challenge 2026 &mdash; evaluate finalists with a transparent weighted rubric, rank the results and let everyone vote for their favorite idea.</p>
    <div class="cards">
        <div class="card">
            <h2>Favorite Idea Voting</h2>
            <p>Use your voting code to pick your favorite ideas and support the best innovations.</p>
            <a class="btn" href="{{ route('voting.public.index') }}">Start voting</a>
        </div>
        <div class="card">
            <h2>Judge &amp; Admin Sign-in</h2>
            <p>Evaluate finalist ideas, track progress and review results.</p>
            <a class="btn" href="{{ route('login') }}">Sign in</a>
        </div>
    </div>
    <footer>&copy; 2026 Judging System for Competition &middot; Demo Company &middot; Demo environment with synthetic data</footer>
</div>
</body>
</html>
