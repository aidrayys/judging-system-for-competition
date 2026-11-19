<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PresentationTest extends CompetitionTestCase
{
    public function test_admin_can_upload_a_presentation_to_the_private_disk(): void
    {
        Storage::fake('presentations');
        $admin = User::factory()->admin()->create();
        $finalist = $this->makeFinalist();

        $file = UploadedFile::fake()->create('presentation.pdf', 120, 'application/pdf');

        $this->actingAs($admin)
            ->post("/admin/presentations/{$finalist->id}", ['presentation' => $file])
            ->assertRedirect();

        $finalist->refresh();
        $this->assertTrue($finalist->hasPresentation());
        $this->assertSame('presentation.pdf', $finalist->presentation_original_name);
        $this->assertNotNull($finalist->presentation_path);
        Storage::disk('presentations')->assertExists($finalist->presentation_path);
    }

    public function test_upload_rejects_unsupported_file_types(): void
    {
        Storage::fake('presentations');
        $admin = User::factory()->admin()->create();
        $finalist = $this->makeFinalist();

        $file = UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream');

        $this->actingAs($admin)
            ->from('/admin/presentations')
            ->post("/admin/presentations/{$finalist->id}", ['presentation' => $file])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertFalse($finalist->refresh()->hasPresentation());
    }

    public function test_unauthenticated_users_cannot_download_a_presentation(): void
    {
        Storage::fake('presentations');
        $admin = User::factory()->admin()->create();
        $finalist = $this->makeFinalist();

        $file = UploadedFile::fake()->create('deck.pdf', 20, 'application/pdf');
        $this->actingAs($admin)->post("/admin/presentations/{$finalist->id}", ['presentation' => $file])->assertRedirect();

        // End the administrator session: the following requests are anonymous.
        $this->post('/logout')->assertRedirect('/login');

        $this->get("/presentations/{$finalist->id}/download")->assertRedirect('/login');
        $this->get("/presentations/{$finalist->id}/open")->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_judge_can_open_and_download_an_authorized_presentation(): void
    {
        Storage::fake('presentations');
        $admin = User::factory()->admin()->create();
        $finalist = $this->makeFinalist();

        $file = UploadedFile::fake()->create('deck.pdf', 20, 'application/pdf');
        $this->actingAs($admin)->post("/admin/presentations/{$finalist->id}", ['presentation' => $file])->assertRedirect();

        $this->actingAs($this->judge)
            ->get("/presentations/{$finalist->id}/download")
            ->assertOk();

        $this->actingAs($this->judge)
            ->get("/presentations/{$finalist->id}/open")
            ->assertOk();
    }

    public function test_a_pdf_is_served_inline_so_the_browser_can_preview_it(): void
    {
        Storage::fake('presentations');
        $admin = User::factory()->admin()->create();
        $finalist = $this->makeFinalist();

        $file = UploadedFile::fake()->create('deck.pdf', 20, 'application/pdf');
        $this->actingAs($admin)->post("/admin/presentations/{$finalist->id}", ['presentation' => $file])->assertRedirect();

        $response = $this->actingAs($this->judge)->get("/presentations/{$finalist->id}/open");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition', 'inline; filename=deck.pdf');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');

        // The download is an attachment.
        $download = $this->actingAs($this->judge)->get("/presentations/{$finalist->id}/download");
        $download->assertOk();
        $download->assertHeader('Content-Disposition', 'attachment; filename=deck.pdf');
    }

    public function test_a_powerpoint_file_is_downloaded_instead_of_faked_as_a_preview(): void
    {
        Storage::fake('presentations');
        $admin = User::factory()->admin()->create();
        $finalist = $this->makeFinalist();

        $file = UploadedFile::fake()->create(
            'deck.pptx',
            20,
            'application/vnd.openxmlformats-officedocument.presentationml.presentation'
        );
        $this->actingAs($admin)->post("/admin/presentations/{$finalist->id}", ['presentation' => $file])->assertRedirect();

        // "View" must not pretend that a PowerPoint file is previewed: the
        // file is delivered as a download.
        $response = $this->actingAs($this->judge)->get("/presentations/{$finalist->id}/open");

        $response->assertOk();
        $response->assertHeader('Content-Disposition', 'attachment; filename=deck.pptx');
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_the_evaluation_page_offers_one_primary_and_one_secondary_action(): void
    {
        Storage::fake('presentations');
        $admin = User::factory()->admin()->create();
        $finalist = $this->makeFinalist();

        $file = UploadedFile::fake()->create('deck.pptx', 20, 'application/vnd.openxmlformats-officedocument.presentationml.presentation');
        $this->actingAs($admin)->post("/admin/presentations/{$finalist->id}", ['presentation' => $file])->assertRedirect();

        $html = $this->actingAs($this->judge)
            ->get("/judge/category/{$this->category->id}/finalist/{$finalist->id}")
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('View Presentation', $html);
        $this->assertStringContainsString('Download file', $html);
        $this->assertStringNotContainsString('Open Presentation', $html);
        $this->assertStringNotContainsString('Download Presentation', $html);
        // The fallback message is part of the page.
        $this->assertStringContainsString(
            'PowerPoint preview is unavailable in this browser. The presentation has been downloaded; open it in Microsoft PowerPoint.',
            preg_replace('/\s+/', ' ', $html)
        );
    }

    public function test_the_private_path_is_never_exposed(): void
    {
        Storage::fake('presentations');
        $admin = User::factory()->admin()->create();
        $finalist = $this->makeFinalist();

        $file = UploadedFile::fake()->create('deck.pdf', 20, 'application/pdf');
        $this->actingAs($admin)->post("/admin/presentations/{$finalist->id}", ['presentation' => $file])->assertRedirect();

        $finalist->refresh();

        $response = $this->actingAs($this->judge)->get("/presentations/{$finalist->id}/open");
        $response->assertOk();
        $body = file_get_contents($response->baseResponse->getFile()->getPathname())
            .implode("\n", array_map(fn ($v) => implode(',', $v), $response->headers->all()));

        $this->assertStringNotContainsString('storage/app', $body);
        $this->assertStringNotContainsString($finalist->presentation_path, $body);
    }

    private function uploadPdf(): \App\Models\Finalist
    {
        Storage::fake('presentations');
        $admin = User::factory()->admin()->create();
        $finalist = $this->makeFinalist();
        $file = UploadedFile::fake()->createWithContent('deck.pdf', '%PDF-1.4 '.str_repeat('x', 5000));
        $this->actingAs($admin)->post("/admin/presentations/{$finalist->id}", ['presentation' => $file])->assertRedirect();

        return $finalist->fresh();
    }

    public function test_pdf_view_supports_range_requests_and_validators(): void
    {
        $finalist = $this->uploadPdf();

        $full = $this->actingAs($this->judge)->get("/presentations/{$finalist->id}/open");
        $full->assertOk();
        $full->assertHeader('Accept-Ranges', 'bytes');
        $full->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('inline', (string) $full->headers->get('Content-Disposition'));
        $cache = (string) $full->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cache);
        $this->assertStringContainsString('no-cache', $cache);
        $this->assertStringNotContainsString('public', $cache);
        $etag = (string) $full->headers->get('ETag');
        $this->assertNotSame('', $etag);

        $this->actingAs($this->judge)
            ->get("/presentations/{$finalist->id}/open", ['Range' => 'bytes=0-99'])
            ->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 0-99/'.$finalist->presentation_size);

        $this->actingAs($this->judge)
            ->get("/presentations/{$finalist->id}/open", ['If-None-Match' => $etag])
            ->assertStatus(304);
    }

    public function test_pdf_view_uses_a_newer_optimized_copy_but_download_keeps_the_original(): void
    {
        $finalist = $this->uploadPdf();
        $disk = Storage::disk('presentations');
        $disk->put('optimized/'.$finalist->presentation_path, '%PDF-1.4 small');
        touch($disk->path('optimized/'.$finalist->presentation_path), time() + 5);

        $view = $this->actingAs($this->judge)->get("/presentations/{$finalist->id}/open");
        $this->assertSame('%PDF-1.4 small', file_get_contents($view->baseResponse->getFile()->getPathname()));

        $download = $this->actingAs($this->judge)->get("/presentations/{$finalist->id}/download");
        $this->assertSame($finalist->presentation_size, strlen($download->streamedContent()));

        // A stale copy (older than the original) is ignored.
        touch($disk->path('optimized/'.$finalist->presentation_path), time() - 3600);
        $view = $this->actingAs($this->judge)->get("/presentations/{$finalist->id}/open");
        $this->assertSame($disk->path($finalist->presentation_path), $view->baseResponse->getFile()->getPathname());
    }

    public function test_range_and_conditional_requests_still_require_login(): void
    {
        $finalist = $this->uploadPdf();
        auth()->logout();

        $this->get("/presentations/{$finalist->id}/open", ['Range' => 'bytes=0-99'])->assertRedirect();
        $this->get("/presentations/{$finalist->id}/open", ['If-None-Match' => '"x"'])->assertRedirect();
    }

    public function test_a_missing_file_produces_a_useful_error(): void
    {
        Storage::fake('presentations');
        $admin = User::factory()->admin()->create();
        $finalist = $this->makeFinalist();

        $file = UploadedFile::fake()->create('deck.pdf', 20, 'application/pdf');
        $this->actingAs($admin)->post("/admin/presentations/{$finalist->id}", ['presentation' => $file])->assertRedirect();

        // The record exists, but the file is gone.
        Storage::disk('presentations')->delete($finalist->fresh()->presentation_path);

        $this->actingAs($this->judge)
            ->get("/presentations/{$finalist->id}/open")
            ->assertNotFound();

        $this->actingAs($this->judge)
            ->get("/presentations/{$finalist->id}/download")
            ->assertNotFound();
    }

    public function test_judge_cannot_upload_or_remove_presentations(): void
    {
        Storage::fake('presentations');
        $finalist = $this->makeFinalist();

        $this->actingAs($this->judge)
            ->post("/admin/presentations/{$finalist->id}", ['presentation' => UploadedFile::fake()->create('deck.pdf', 5, 'application/pdf')])
            ->assertForbidden();

        $this->actingAs($this->judge)
            ->delete("/admin/presentations/{$finalist->id}")
            ->assertForbidden();
    }

    public function test_missing_presentation_never_produces_a_broken_link(): void
    {
        $finalist = $this->makeFinalist();

        $this->actingAs($this->judge)
            ->get("/presentations/{$finalist->id}/download")
            ->assertNotFound();

        $this->actingAs($this->judge)
            ->get("/judge/category/{$this->category->id}/finalist/{$finalist->id}")
            ->assertOk()
            ->assertSee('Presentation has not been uploaded yet.');
    }

    public function test_admin_can_remove_a_presentation(): void
    {
        Storage::fake('presentations');
        $admin = User::factory()->admin()->create();
        $finalist = $this->makeFinalist();

        $file = UploadedFile::fake()->create('deck.pdf', 10, 'application/pdf');
        $this->actingAs($admin)->post("/admin/presentations/{$finalist->id}", ['presentation' => $file])->assertRedirect();

        $path = $finalist->refresh()->presentation_path;

        $this->actingAs($admin)
            ->delete("/admin/presentations/{$finalist->id}")
            ->assertRedirect();

        $finalist->refresh();
        $this->assertFalse($finalist->hasPresentation());
        Storage::disk('presentations')->assertMissing($path);
    }

    public function test_presentation_file_is_not_publicly_browsable(): void
    {
        Storage::fake('presentations');
        $admin = User::factory()->admin()->create();
        $finalist = $this->makeFinalist();

        $file = UploadedFile::fake()->create('deck.pdf', 10, 'application/pdf');
        $this->actingAs($admin)->post("/admin/presentations/{$finalist->id}", ['presentation' => $file])->assertRedirect();

        $path = $finalist->refresh()->presentation_path;

        // The file must not be reachable through the public storage symlink.
        $this->assertFileDoesNotExist(public_path('storage/presentations/'.$path));
    }
}
