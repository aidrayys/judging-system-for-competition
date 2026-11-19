@extends('layouts.app')
@section('title','Choose Your Favorite Idea - Judging System for Competition')
@section('content')
<style>
    .finalist-card {
        position: relative;
        cursor: pointer;
        transition: all .15s ease;
        user-select: none;
        -webkit-tap-highlight-color: transparent;
    }
    .finalist-card.selected {
        background-color: #dbeafe;
        border-color: #1e3a5f;
        border-width: 2px;
    }
    .finalist-card .check {
        position: absolute;
        top: 12px;
        right: 12px;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: #1e3a5f;
        color: #fff;
        display: none;
        align-items: center;
        justify-content: center;
        font-size: 14px;
    }
    .finalist-card.selected .check { display: flex; }
    .finalist-card:focus-visible { outline: 3px solid #93c5fd; outline-offset: 2px; }
    .finalist-card.locked { opacity: .55; cursor: not-allowed; }
    .limit-note { display: none; font-size: 12px; color: #b45309; margin-top: 2px; }
    .limit-note.show { display: block; }
    .toast {
        position: fixed;
        top: 16px;
        left: 50%;
        transform: translateX(-50%);
        background: #b91c1c;
        color: #fff;
        padding: 10px 18px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,.2);
        display: none;
        z-index: 60;
        font-size: 14px;
    }
    .modal-backdrop {
        position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 70; display: none;
        align-items: center; justify-content: center; padding: 16px;
    }
    .modal { background: #fff; border-radius: 16px; max-width: 420px; width: 100%; padding: 24px; }
</style>

<div class="toast" id="toast"></div>

<div class="max-w-4xl mx-auto px-4 py-6 pb-32">
    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700" role="alert">
            @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif
    <div class="text-center mb-6">
        <h1 class="text-2xl font-bold">Choose Your Favorite Ideas</h1>
        <p class="text-sm mt-1">You have <span class="font-bold">2 votes</span>. Choose <span class="font-bold">1 or 2 different ideas</span> &mdash; 1 idea = 1 vote.</p>
    </div>

    <form id="voteForm" method="POST" action="{{ route('voting.public.submit') }}">
        @csrf
        @foreach($categories as $category)
        <div class="mb-8">
            <h2 class="text-xl font-semibold mb-3">{{ $category->name }}</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($category->finalists as $finalist)
                <div class="finalist-card bg-white p-4 rounded-lg shadow border border-slate-300"
                     role="checkbox" aria-checked="false" tabindex="0"
                     data-id="{{ $finalist->id }}"
                     data-name="{{ $finalist->code }} - {{ $finalist->name }}">
                    <div class="check">✓</div>
                    <div class="font-bold pr-8">{{ $finalist->code }} - {{ $finalist->name }}</div>
                    <div class="text-sm text-slate-500">{{ $finalist->department }}</div>
                    <div class="text-sm mt-1 text-slate-700">{{ $finalist->project_title }}</div>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
    </form>
</div>

<div class="fixed bottom-0 left-0 right-0 bg-white border-t border-slate-200 p-4 z-50">
    <div class="max-w-4xl mx-auto flex justify-between items-center">
        <div>
            <div class="font-medium">Votes selected: <span id="used">0</span>/2</div>
            <div id="hint" class="text-xs text-slate-500">Choose 1 or 2 ideas</div>
            <div id="limitNote" class="limit-note">Maximum 2 ideas. Deselect one to change your choice.</div>
        </div>
        <button type="button" id="submitBtn" class="bg-navy-900 text-white px-6 py-3 rounded-lg disabled:opacity-50" disabled>Confirm</button>
    </div>
</div>

<div class="modal-backdrop" id="confirmModal">
    <div class="modal">
        <h3 class="text-lg font-bold mb-2">Confirm Your Votes</h3>
        <p class="text-sm mb-4">Make sure your choices are correct:</p>
        <ul id="confirmList" class="text-sm mb-6 space-y-2"></ul>
        <div class="flex gap-3">
            <button type="button" id="cancelBtn" class="flex-1 py-2 rounded-lg border border-slate-300">Cancel</button>
            <button type="button" id="okBtn" class="flex-1 py-2 rounded-lg bg-navy-900 text-white">Submit</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const MAX_VOTES = 2;
const selected = new Set(); // finalist ids, each = 1 vote

function render() {
    const full = selected.size >= MAX_VOTES;
    document.querySelectorAll('.finalist-card').forEach(card => {
        const on = selected.has(card.dataset.id);
        card.classList.toggle('selected', on);
        card.classList.toggle('locked', full && !on);
        card.setAttribute('aria-checked', on ? 'true' : 'false');
        card.setAttribute('aria-disabled', full && !on ? 'true' : 'false');
    });

    const used = selected.size;
    document.getElementById('used').textContent = used;
    document.getElementById('submitBtn').disabled = used < 1;
    document.getElementById('limitNote').classList.toggle('show', full);

    const hint = document.getElementById('hint');
    if (used === 0) hint.textContent = 'Choose 1 or 2 ideas';
    else if (used === 1) hint.textContent = 'Status: 1 vote ready to submit.';
    else hint.textContent = 'Status: Ready to submit.';
}

let toastTimer;
function showToast(msg) {
    const toast = document.getElementById('toast');
    toast.textContent = msg;
    toast.style.display = 'block';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.style.display = 'none', 2500);
}

function toggle(card) {
    const id = card.dataset.id;
    if (selected.has(id)) {
        selected.delete(id);
    } else {
        if (selected.size >= MAX_VOTES) {
            showToast('You can choose at most 2 ideas. Deselect one first.');
            return;
        }
        selected.add(id);
    }
    render();
}

document.querySelectorAll('.finalist-card').forEach(card => {
    card.addEventListener('click', () => toggle(card));
    card.addEventListener('keydown', (e) => {
        if (e.key === ' ' || e.key === 'Enter') { e.preventDefault(); toggle(card); }
    });
});

function syncForm() {
    const form = document.getElementById('voteForm');
    form.querySelectorAll('input[name^="votes"]').forEach(e => e.remove());
    selected.forEach(id => {
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'votes[]';
        inp.value = id;
        form.appendChild(inp);
    });
}

document.getElementById('submitBtn').addEventListener('click', () => {
    if (selected.size < 1) return;
    const list = document.getElementById('confirmList');
    list.innerHTML = '';
    selected.forEach(id => {
        const card = document.querySelector('.finalist-card[data-id="' + id + '"]');
        const li = document.createElement('li');
        li.textContent = card.dataset.name + ' — 1 vote';
        list.appendChild(li);
    });
    document.getElementById('confirmModal').style.display = 'flex';
});

document.getElementById('cancelBtn').addEventListener('click', () => {
    document.getElementById('confirmModal').style.display = 'none';
});

let submitting = false;
document.getElementById('okBtn').addEventListener('click', (e) => {
    if (submitting) return; // prevent double submit
    submitting = true;
    e.currentTarget.disabled = true;
    e.currentTarget.textContent = 'Mengirim...';
    syncForm();
    document.getElementById('voteForm').submit();
});

render();
</script>
@endpush
