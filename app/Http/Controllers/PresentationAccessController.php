<?php

namespace App\Http\Controllers;

use App\Models\Finalist;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Authenticated access to private presentation files.
 *
 * Files are never served from a public URL: they are streamed from the private
 * "presentations" disk through an authorized controller route. The path always
 * comes from the database record, never from the request, so no path traversal
 * is possible.
 *
 * Preview behaviour:
 *  - a PDF is served inline (Content-Type: application/pdf) and opens in the
 *    browser tab
 *  - every other format (PowerPoint, ...) cannot be rendered by a browser
 *    without a third party viewer. Those files are sent as a download, so
 *    nothing is pretended to be a preview.
 */
class PresentationAccessController extends Controller
{
    /**
     * The file types a browser can display on its own.
     *
     * @var array<int, string>
     */
    public const PREVIEWABLE_EXTENSIONS = ['pdf'];

    /**
     * Open the presentation in the browser (inline).
     *
     * A PDF is streamed inline. Any other format is delivered as a download,
     * because no browser can render it without an external viewer - and the
     * file must not be published just to make an external viewer possible.
     */
    public function show(Request $request, Finalist $finalist)
    {
        $this->authorizeAccess($request, $finalist);

        $disk = Storage::disk($finalist->presentation_disk);
        if (! $disk->exists($finalist->presentation_path)) {
            abort(404, 'The presentation file is no longer available.');
        }

        if (! $this->isPreviewable($finalist)) {
            return $this->download($request, $finalist);
        }

        // Performance (see docs/presentation-performance/):
        // A BinaryFileResponse (instead of a PHP fpassthru stream) gives the
        // browser's PDF viewer a Content-Length, "Accept-Ranges: bytes" and
        // real 206 Partial Content answers, plus ETag/Last-Modified so a
        // second open costs a tiny 304 instead of a full re-download.
        // "private, no-cache": never stored by shared caches (ngrok edge,
        // proxies) and every reuse is revalidated through this route, so the
        // auth/role check above still runs on every open.
        $response = new BinaryFileResponse(
            $this->inlineFilePath($disk, $finalist),
            200,
            [
                'Content-Type' => $this->contentType($finalist),
                'X-Content-Type-Options' => 'nosniff',
            ],
            false,
            'inline',
            true,
            true
        );
        $response->setContentDisposition(
            'inline',
            (string) $finalist->presentation_original_name,
            $this->asciiFallbackName($finalist)
        );
        $response->headers->set('Cache-Control', 'private, no-cache');
        $response->isNotModified($request);

        return $response;
    }

    /**
     * The file shown inline: a lossless, size-optimized copy (font subsetting
     * and object clean-up, see scripts/optimize-presentations.py) when one
     * exists and is newer than the original; otherwise the original itself.
     * The original is never modified and is always what "Download" returns.
     */
    private function inlineFilePath($disk, Finalist $finalist): string
    {
        $original = $disk->path($finalist->presentation_path);
        $optimized = $disk->path('optimized/'.$finalist->presentation_path);

        if (is_file($optimized) && filemtime($optimized) >= filemtime($original) && filesize($optimized) > 0) {
            return $optimized;
        }

        return $original;
    }

    private function asciiFallbackName(Finalist $finalist): string
    {
        $name = (string) $finalist->presentation_original_name;
        $ascii = preg_replace('/[^\x20-\x7E]|[\/\\\\%"]/', '_', $name);

        return $ascii !== '' ? $ascii : 'presentation.pdf';
    }

    /**
     * Download the presentation as an attachment.
     */
    public function download(Request $request, Finalist $finalist): StreamedResponse
    {
        $this->authorizeAccess($request, $finalist);

        $disk = Storage::disk($finalist->presentation_disk);
        if (! $disk->exists($finalist->presentation_path)) {
            abort(404, 'The presentation file is no longer available.');
        }

        return $disk->download(
            $finalist->presentation_path,
            $finalist->presentation_original_name,
            [
                'Content-Type' => $this->contentType($finalist),
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    /**
     * Can this file be displayed by the browser itself?
     */
    public function isPreviewable(Finalist $finalist): bool
    {
        return in_array($this->extension($finalist), self::PREVIEWABLE_EXTENSIONS, true);
    }

    /**
     * The lower case file extension of the stored presentation.
     */
    public function extension(Finalist $finalist): string
    {
        $name = (string) ($finalist->presentation_original_name ?? '');

        return strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
    }

    /**
     * The content type that is sent to the browser.
     *
     * A PDF always gets the exact PDF content type, so the browser renders it
     * inline instead of downloading it.
     */
    public function contentType(Finalist $finalist): string
    {
        if ($this->extension($finalist) === 'pdf') {
            return 'application/pdf';
        }

        $mime = (string) ($finalist->presentation_mime_type ?? '');

        return $mime !== '' ? $mime : 'application/octet-stream';
    }

    private function authorizeAccess(Request $request, Finalist $finalist): void
    {
        $user = $request->user();

        abort_if(! $user, 403, 'Authentication required.');
        abort_unless(in_array($user->role, [User::ROLE_ADMIN, User::ROLE_JUDGE], true), 403);
        abort_unless($finalist->hasPresentation(), 404, 'No presentation has been uploaded for this finalist.');
    }
}
