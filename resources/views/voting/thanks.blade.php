@extends('layouts.app')
@section('title','Favorite Idea Leaderboard - Judging System for Competition')
@section('content')
@php
    // Light wordmark used on the dark leaderboard background.
    $silverLogo = 'images/wordmark-light.svg';
    // Visual order: Rank 2 (left), Rank 1 (center), Rank 3 (right).
    $steps = [
        2 => ['label' => 'RANK 2', 'tone' => 'silver'],
        1 => ['label' => 'RANK 1', 'tone' => 'gold'],
        3 => ['label' => 'RANK 3', 'tone' => 'bronze'],
    ];
@endphp
<style>
    .lb {
        --navy: #0f1f3d; --purple: #46178f; --violet: #6d28d9;
        --ink: #142B50;
        --gold: #FFD54F; --gold-hi: #ffe58a; --gold-d: #e6b92e;
        --silver: #DCE3EC; --silver-hi: #f1f4f8; --silver-d: #b9c3d1;
        --bronze: #CD8B55; --bronze-hi: #e0a876; --bronze-d: #b0703e;
        position: relative; overflow: hidden;
        border-radius: 24px; padding: 28px clamp(10px, 3vw, 32px) 40px;
        background:
            radial-gradient(circle at 15% 10%, rgba(109,40,217,.55), transparent 45%),
            radial-gradient(circle at 85% 20%, rgba(56,189,248,.25), transparent 40%),
            linear-gradient(160deg, var(--navy) 0%, #1e1b4b 50%, var(--purple) 100%);
        color: #fff; font-family: inherit;
    }
    .lb__inner { position: relative; z-index: 2; max-width: 960px; margin: 0 auto; }
    .lb__head { text-align: center; margin-bottom: 28px; }
    .lb__logo {
        display: block; width: auto; height: clamp(44px, 9vw, 84px); max-width: 80%;
        object-fit: contain; margin: 0 auto 14px;
    }
    .lb__badge {
        display: inline-block; margin-top: 10px; padding: 4px 12px; border-radius: 999px;
        background: rgba(255,213,79,.18); border: 1px solid rgba(255,213,79,.6);
        color: #FFD54F; font-size: 12px; font-weight: 800; letter-spacing: .08em;
    }
    .lb__title {
        font-size: clamp(22px, 5.5vw, 44px); font-weight: 900; letter-spacing: .04em; line-height: 1.1;
        text-shadow: 0 4px 0 rgba(0,0,0,.25);
    }
    .lb__sub { margin-top: 8px; font-size: clamp(13px, 2.4vw, 17px); color: #c7d2fe; font-weight: 600; }

    .podium { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: clamp(6px, 2vw, 18px); align-items: end; }
    .step { display: flex; flex-direction: column; align-items: stretch; min-width: 0; }
    .step__people { display: flex; flex-direction: column; gap: 8px; margin-bottom: 10px; opacity: 0; animation: lbFade .5s ease forwards; }
    .step--gold .trophy { animation: lbBounce 1.6s ease-in-out 1.4s infinite; }
    .entry {
        background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.18);
        border-radius: 12px; padding: 8px 10px; text-align: center; backdrop-filter: blur(4px);
        overflow-wrap: anywhere; word-break: break-word; hyphens: auto;
    }
    .entry__name { font-weight: 800; font-size: clamp(12px, 2.4vw, 17px); line-height: 1.25; }
    .entry__title { margin-top: 3px; font-size: clamp(11px, 2vw, 14px); color: #e0e7ff; line-height: 1.3; }

    .block {
        border-radius: 12px 12px 0 0; display: flex; flex-direction: column; align-items: center; justify-content: flex-start;
        padding-top: 14px; color: var(--ink); font-weight: 900; transform-origin: bottom;
        transform: scaleY(0); animation: lbRise .8s cubic-bezier(.2,.9,.3,1.2) forwards;
        box-shadow: inset 0 -10px 0 rgba(0,0,0,.10), 0 10px 30px rgba(0,0,0,.35);
    }
    .block__rank { font-size: clamp(30px, 8vw, 64px); line-height: 1; }
    .block__label { font-size: clamp(10px, 2vw, 14px); letter-spacing: .08em; margin-top: 6px; font-weight: 900; }
    .medal {
        width: clamp(38px, 8vw, 58px); height: clamp(38px, 8vw, 58px); margin: 0 auto 6px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: 900; font-size: clamp(16px, 3.4vw, 24px); color: var(--ink);
        border: 3px solid rgba(255,255,255,.85); box-shadow: 0 4px 12px rgba(0,0,0,.35);
    }
    .step--gold .medal   { background: radial-gradient(circle at 35% 30%, var(--gold-hi), var(--gold) 60%, var(--gold-d)); }
    .step--silver .medal { background: radial-gradient(circle at 35% 30%, var(--silver-hi), var(--silver) 60%, var(--silver-d)); }
    .step--bronze .medal { background: radial-gradient(circle at 35% 30%, var(--bronze-hi), var(--bronze) 60%, var(--bronze-d)); }
    .trophy { font-size: clamp(30px, 7vw, 52px); text-align: center; line-height: 1; margin-bottom: 4px; }
    .step--gold .entry { border-color: rgba(255,213,79,.7); }
    .step--silver .entry { border-color: rgba(220,227,236,.6); }
    .step--bronze .entry { border-color: rgba(205,139,85,.75); }
    .step--gold   .block { height: clamp(150px, 30vw, 240px); background: linear-gradient(180deg, var(--gold-hi), var(--gold) 55%, var(--gold-d)); animation-delay: .9s; }
    .step--silver .block { height: clamp(110px, 22vw, 180px); background: linear-gradient(180deg, var(--silver-hi), var(--silver) 55%, var(--silver-d)); animation-delay: .5s; }
    .step--bronze .block { height: clamp(80px, 16vw, 130px);  background: linear-gradient(180deg, var(--bronze-hi), var(--bronze) 55%, var(--bronze-d)); animation-delay: .1s; }
    .step--gold   .step__people { animation-delay: 1.5s; }
    .step--silver .step__people { animation-delay: 1.1s; }
    .step--bronze .step__people { animation-delay: .7s; }
    .step--empty .block { opacity: .35; }
    .entry--empty { font-size: 12px; color: #c7d2fe; font-style: italic; }
    .podium__floor { height: 10px; border-radius: 0 0 10px 10px; background: rgba(0,0,0,.3); }

    .msg {
        margin: 32px auto 0; max-width: 640px; text-align: center; padding: 24px 20px;
        border-radius: 20px; background: #fff; color: var(--navy);
        box-shadow: 0 20px 40px rgba(0,0,0,.35); border-top: 6px solid var(--gold);
        opacity: 0; animation: lbFade .6s ease 2s forwards;
    }
    .msg__title { font-size: clamp(20px, 4.5vw, 30px); font-weight: 900; color: var(--purple); }
    .msg__body { margin-top: 10px; font-size: clamp(14px, 2.6vw, 17px); line-height: 1.55; color: #334155; }
    .msg__slogan {
        display: inline-block; margin-top: 16px; padding: 8px 16px; border-radius: 999px;
        background: linear-gradient(90deg, var(--navy), var(--violet)); color: #fff;
        font-weight: 800; font-size: clamp(13px, 2.6vw, 16px); letter-spacing: .02em;
    }
    .lb__empty { text-align: center; color: #c7d2fe; margin: 10px 0 24px; font-size: 14px; }
    .lb__back { display: block; text-align: center; margin-top: 20px; color: #c7d2fe; font-size: 14px; text-decoration: underline; }

    .confetti { position: absolute; inset: 0; pointer-events: none; z-index: 1; overflow: hidden; }
    .confetti i {
        position: absolute; top: -20px; width: 8px; height: 14px; border-radius: 2px; opacity: .9;
        animation: lbFall linear forwards; will-change: transform;
    }

    @keyframes lbRise { to { transform: scaleY(1); } }
    @keyframes lbFade { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
    @keyframes lbBounce { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }
    @keyframes lbFall { to { transform: translate3d(var(--dx), 110vh, 0) rotate(720deg); } }

    @media (max-width: 480px) {
        .lb { padding-top: 20px; }
        .entry { padding: 6px 6px; border-radius: 10px; }
    }
    @media (prefers-reduced-motion: reduce) {
        .block, .step__people, .msg { animation: none !important; transform: none !important; opacity: 1 !important; }
        .step--gold .trophy { animation: none; }
        .confetti { display: none; }
    }
</style>

<section class="lb" aria-labelledby="lbTitle">
    <div class="confetti" id="confetti" aria-hidden="true"></div>

    <div class="lb__inner">
        <header class="lb__head">
            <img src="{{ asset($silverLogo) }}" alt="Judging System for Competition" class="lb__logo" width="330" height="64">
            <h1 class="lb__title" id="lbTitle">FAVORITE IDEA LEADERBOARD</h1>
            <p class="lb__sub">Innovation Challenge 2026 &mdash; Top 3 Most Voted Ideas</p>
            @if ($provisional)
                <span class="lb__badge">PROVISIONAL RANKING</span>
            @endif
        </header>

        @if ($podium->isEmpty())
            <p class="lb__empty">No idea has received votes yet.</p>
        @endif

        <div class="podium" role="list">
            @foreach ($steps as $rank => $meta)
                @php $step = $podium->get($rank); @endphp
                <div class="step step--{{ $meta['tone'] }} {{ $step ? '' : 'step--empty' }}" role="listitem"
                     aria-label="{{ $meta['label'] }}">
                    <div class="step__people">
                        @if ($step)
                            @if ($rank === 1)
                                <div class="trophy" aria-hidden="true">🏆</div>
                            @else
                                <div class="medal" aria-hidden="true">{{ $rank }}</div>
                            @endif
                            <div class="entry">
                                <div class="entry__name">{{ $step['name'] }}</div>
                                @if ($step['title'] !== '')
                                    <div class="entry__title">{{ $step['title'] }}</div>
                                @endif
                            </div>
                        @else
                            <div class="entry entry--empty">None yet</div>
                        @endif
                    </div>
                    <div class="block">
                        <span class="block__rank">{{ $rank }}</span>
                        <span class="block__label">{{ $meta['label'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="podium__floor" aria-hidden="true"></div>

        <div class="msg" role="status">
            <div class="msg__title">Thank you for voting!</div>
            <p class="msg__body">There is only one Favorite Idea winner. Invite your colleagues to come and vote too!</p>
            <div class="msg__slogan">Collect ideas. Evaluate fairly. Celebrate innovation.</div>
        </div>

        <a href="{{ url('/') }}" class="lb__back">Back to home</a>
    </div>
</section>
@endsection

@push('scripts')
<script>
(function () {
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const box = document.getElementById('confetti');
    if (!box) return;
    const colors = ['#f5c518', '#d6dbe3', '#d98c4a', '#a78bfa', '#38bdf8', '#f472b6', '#ffffff'];
    const n = window.innerWidth < 600 ? 40 : 80; // lighter on phones
    const frag = document.createDocumentFragment();
    for (let i = 0; i < n; i++) {
        const p = document.createElement('i');
        p.style.left = (Math.random() * 100) + '%';
        p.style.background = colors[i % colors.length];
        p.style.setProperty('--dx', ((Math.random() - .5) * 200) + 'px');
        p.style.animationDuration = (3 + Math.random() * 3) + 's';
        p.style.animationDelay = (0.8 + Math.random() * 2) + 's';
        p.style.transform = 'rotate(' + (Math.random() * 360) + 'deg)';
        frag.appendChild(p);
    }
    box.appendChild(frag);
    setTimeout(() => box.remove(), 9000);
})();
</script>
@endpush
