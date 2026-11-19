{{--
    Presentation actions.

    @param \App\Models\Finalist $finalist
    @param string|null $primaryClass  optional class for the primary button

    A PDF is previewed inline in a new browser tab. Every other format (for
    example PowerPoint) cannot be rendered by a browser without a third party
    viewer, so "View Presentation" delivers the file as a download and explains
    how to open it - nothing is pretended to be a preview.
--}}
@php
    $extension = strtolower((string) pathinfo((string) ($finalist->presentation_original_name ?? ''), PATHINFO_EXTENSION));
    $isPdf = $extension === 'pdf';
    $primaryClass = $primaryClass ?? 'rounded-md bg-navy-900 px-4 py-2 text-sm font-semibold text-white hover:bg-navy-800';
@endphp
<div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2">
    <a href="{{ $isPdf ? route('presentations.open', $finalist) : route('presentations.download', $finalist) }}"
       target="_blank" rel="noopener"
       @if (! $isPdf) data-presentation-fallback @endif
       class="{{ $primaryClass }}">
        View Presentation
    </a>
    <a href="{{ route('presentations.download', $finalist) }}"
       class="text-sm font-medium text-navy-700 underline hover:text-navy-900">
        Download file
    </a>
</div>

@if ($isPdf)
    <p class="mt-2 text-xs text-slate-500">
        The PDF opens in a new browser tab. Use <span class="font-medium">Download file</span> to save a copy.
    </p>
@else
    <p class="mt-2 text-xs text-slate-500">
        This browser cannot display a {{ strtoupper($extension) }} file. <span class="font-medium">View Presentation</span>
        downloads the presentation so you can open it in Microsoft PowerPoint.
    </p>
    <p id="presentation-fallback-message" class="mt-2 hidden rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
        PowerPoint preview is unavailable in this browser. The presentation has been downloaded; open it in Microsoft PowerPoint.
    </p>
@endif

@once
    <script>
        // After a "View Presentation" click on a file that cannot be previewed,
        // the page explains what happened instead of pretending a preview ran.
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-presentation-fallback]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var message = document.getElementById('presentation-fallback-message');
                    if (message) {
                        message.classList.remove('hidden');
                    }
                });
            });
        });
    </script>
@endonce
