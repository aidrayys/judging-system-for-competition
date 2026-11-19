@php
    $percent = max(0, min(100, (float) ($percent ?? 0)));
    $tone = $percent >= 100 ? 'bg-emerald-500' : ($percent >= 50 ? 'bg-navy-500' : 'bg-gold-500');
    $class = $class ?? 'h-2 w-full overflow-hidden rounded-full bg-slate-200';
@endphp
<div class="{{ $class }}">
    <div class="h-full rounded-full {{ $tone }}" style="width: {{ $percent }}%"></div>
</div>
