<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Finalist;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PresentationController extends Controller
{
    public const MAX_KB = 250 * 1024; // 250 MB (256000 KB)

    public const ALLOWED_EXTENSIONS = ['ppt', 'pptx', 'pdf'];

    public const ALLOWED_MIMES = [
        'application/pdf',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/x-mspowerpoint',
        'application/octet-stream', // some Windows installs report generic type for .ppt
        'application/zip', // some .pptx uploads are reported as zip
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $query = Finalist::query()->with('category')->orderBy('category_id')->orderBy('display_order')->orderBy('id');

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->trim().'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('project_title', 'like', $term)
                    ->orWhere('department', 'like', $term)
                    ->orWhere('code', 'like', $term)
                    ->orWhere('presentation_original_name', 'like', $term);
            });
        }

        if ($request->filled('category')) {
            $query->where('category_id', $request->integer('category'));
        }

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            if ($status === 'uploaded') {
                $query->whereNotNull('presentation_path');
            } elseif ($status === 'missing') {
                $query->whereNull('presentation_path');
            }
        }

        return view('admin.presentations.index', [
            'finalists' => $query->paginate(20)->withQueryString(),
            'categories' => Category::orderBy('display_order')->get(),
            'maxKb' => self::MAX_KB,
        ]);
    }

    public function store(Request $request, Finalist $finalist): RedirectResponse
    {
        $request->validate([
            'presentation' => ['required', 'file', 'max:'.self::MAX_KB],
        ]);

        $file = $request->file('presentation');

        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return back()->with('error', 'Only .ppt, .pptx and .pdf files are accepted.');
        }

        $mime = (string) $file->getMimeType();
        $guessed = (string) $file->guessExtension();
        $mimeAllowed = in_array($mime, self::ALLOWED_MIMES, true) || in_array($guessed, self::ALLOWED_EXTENSIONS, true);

        if (! $mimeAllowed) {
            return back()->with('error', 'The uploaded file type is not a valid PowerPoint or PDF document.');
        }

        $originalName = $file->getClientOriginalName();
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
        $storedName = $finalist->code.'_'.now()->format('YmdHis').'_'.bin2hex(random_bytes(6)).'_'.$safeName;

        $disk = Storage::disk('presentations');
        $path = $disk->putFileAs('finalists/'.$finalist->id, $file, $storedName);

        if (! $path) {
            return back()->with('error', 'The file could not be stored. Please check the storage permissions.');
        }

        // Remove the previously stored file (if any)
        if ($finalist->presentation_path && $finalist->presentation_disk) {
            Storage::disk($finalist->presentation_disk)->delete($finalist->presentation_path);
        }

        $finalist->update([
            'presentation_disk' => 'presentations',
            'presentation_path' => $path,
            'presentation_original_name' => $originalName,
            'presentation_mime_type' => $mime,
            'presentation_size' => $file->getSize(),
            'presentation_uploaded_at' => now(),
            'presentation_uploaded_by' => $request->user()->id,
        ]);

        $this->audit->log(
            'presentation.upload',
            'Stored presentation for '.$finalist->code.' - '.$finalist->name,
            Finalist::class,
            $finalist->id,
            ['original_name' => $originalName, 'size' => $file->getSize()]
        );

        return back()->with('status', 'Presentation uploaded for '.$finalist->code.'.');
    }

    public function destroy(Finalist $finalist): RedirectResponse
    {
        if ($finalist->presentation_path && $finalist->presentation_disk) {
            Storage::disk($finalist->presentation_disk)->delete($finalist->presentation_path);
        }

        $finalist->update([
            'presentation_disk' => null,
            'presentation_path' => null,
            'presentation_original_name' => null,
            'presentation_mime_type' => null,
            'presentation_size' => null,
            'presentation_uploaded_at' => null,
            'presentation_uploaded_by' => null,
        ]);

        $this->audit->log('presentation.remove', 'Removed presentation for '.$finalist->code.' - '.$finalist->name, Finalist::class, $finalist->id);

        return back()->with('status', 'Presentation removed for '.$finalist->code.'.');
    }
}
