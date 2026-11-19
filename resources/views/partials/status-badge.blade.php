@php
    $map = [
        'not_started' => ['label' => 'Not Started', 'classes' => 'bg-slate-100 text-slate-700 ring-slate-300'],
        'draft' => ['label' => 'Draft', 'classes' => 'bg-amber-50 text-amber-800 ring-amber-300'],
        'submitted' => ['label' => 'Submitted', 'classes' => 'bg-emerald-50 text-emerald-800 ring-emerald-300'],
        'locked' => ['label' => 'Locked', 'classes' => 'bg-slate-200 text-slate-800 ring-slate-400'],
        'active' => ['label' => 'Active', 'classes' => 'bg-emerald-50 text-emerald-800 ring-emerald-300'],
        'inactive' => ['label' => 'Inactive', 'classes' => 'bg-slate-100 text-slate-600 ring-slate-300'],
        'uploaded' => ['label' => 'Uploaded', 'classes' => 'bg-emerald-50 text-emerald-800 ring-emerald-300'],
        'missing' => ['label' => 'Not uploaded', 'classes' => 'bg-amber-50 text-amber-800 ring-amber-300'],
        'complete' => ['label' => 'Complete', 'classes' => 'bg-emerald-50 text-emerald-800 ring-emerald-300'],
        'locked_results' => ['label' => 'Results Locked', 'classes' => 'bg-rose-50 text-rose-800 ring-rose-300'],
        'released' => ['label' => 'Released', 'classes' => 'bg-emerald-50 text-emerald-800 ring-emerald-300'],
        'pending' => ['label' => 'Ready to release', 'classes' => 'bg-amber-50 text-amber-800 ring-amber-300'],
        'in_progress' => ['label' => 'In Progress', 'classes' => 'bg-amber-50 text-amber-800 ring-amber-300'],
        'official' => ['label' => 'Official', 'classes' => 'bg-emerald-50 text-emerald-800 ring-emerald-300'],
    ];
    $state = $state ?? 'not_started';
    $entry = $map[$state] ?? ['label' => ucfirst(str_replace('_', ' ', $state)), 'classes' => 'bg-slate-100 text-slate-700 ring-slate-300'];
    $class = $class ?? 'inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset';
@endphp
<span class="{{ $class }} {{ $entry['classes'] }}">{{ $entry['label'] }}</span>
