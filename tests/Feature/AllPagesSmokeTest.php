<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\EligibleVoter;
use App\Models\Evaluation;
use App\Models\Finalist;
use App\Models\User;
use App\Models\VotingBallot;
use App\Models\VotingPeriod;
use App\Models\VotingVote;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Full-system smoke test on a seeded test DB (SQLite in-memory, never MySQL):
 * every GET page of every role renders (no 500, undefined variable, broken query),
 * HTML pages are no-store, and every write route answers with a redirect/JSON (PRG).
 */
class AllPagesSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $judge;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('role', 'admin')->firstOrFail();
        $this->judge = User::where('role', 'judge')->firstOrFail();

        VotingPeriod::create(['opens_at' => now()->subHour(), 'closes_at' => now()->addHour(), 'is_open' => true]);
        $voter = EligibleVoter::factory()->create(['kode_unik' => '77777']);
        $ballot = VotingBallot::create(['eligible_voter_id' => $voter->id, 'submitted_at' => now()]);
        VotingVote::create(['voting_ballot_id' => $ballot->id, 'finalist_id' => Finalist::first()->id, 'count' => 1]);
    }

    /** Every parameterless GET route + parameter routes with real IDs. */
    private function adminUrls(): array
    {
        $cat = Category::first();
        $f = Finalist::first();
        $urls = [];
        foreach (Route::getRoutes() as $r) {
            if (in_array('GET', $r->methods(), true) && str_starts_with($r->uri(), 'admin') && ! str_contains($r->uri(), '{')) {
                $urls[] = '/'.$r->uri();
            }
        }
        $urls[] = route('admin.finalists.edit', $f, false);
        $urls[] = route('admin.results.show', $cat, false);
        foreach (Category::all() as $c) {
            $urls[] = '/admin/results/'.$c->id;
        }

        return array_unique($urls);
    }

    public function test_every_admin_page_renders(): void
    {
        // Create one evaluation so the evaluation detail page has data.
        $cat = Category::first();
        $f = Finalist::where('category_id', $cat->id)->first();
        $this->actingAs($this->judge)->postJson("/judge/category/{$cat->id}/finalist/{$f->id}/autosave", [
            'scores' => $cat->activeCriteria()->pluck('id')->mapWithKeys(fn ($id) => [$id => 6])->all(),
        ]);
        $urls = array_merge($this->adminUrls(), ['/admin/evaluations/'.Evaluation::first()?->id]);

        $failures = [];
        foreach ($urls as $u) {
            $res = $this->actingAs($this->admin)->get($u);
            if ($res->getStatusCode() !== 200) {
                $failures[] = "$u => ".$res->getStatusCode();

                continue;
            }
            $type = (string) $res->headers->get('Content-Type');
            if (str_contains($type, 'text/html') && ! str_contains((string) $res->headers->get('Cache-Control'), 'no-store')) {
                $failures[] = "$u => missing no-store";
            }
        }
        $this->assertSame([], $failures, implode("\n", $failures));
        $this->assertGreaterThanOrEqual(15, count($urls));
    }

    public function test_every_judge_page_renders_for_both_categories(): void
    {
        $urls = ['/judge'];
        foreach (Category::all() as $c) {
            foreach (Finalist::where('category_id', $c->id)->where('is_active', true)->get() as $f) {
                $urls[] = "/judge/category/{$c->id}/finalist/{$f->id}";
                $urls[] = "/judge/category/{$c->id}/finalist/{$f->id}/submitted";
            }
        }
        $failures = [];
        foreach ($urls as $u) {
            $res = $this->actingAs($this->judge)->get($u);
            if ($res->getStatusCode() !== 200) {
                $failures[] = "$u => ".$res->getStatusCode();
            }
        }
        $this->assertSame([], $failures, implode("\n", $failures));
        $this->assertGreaterThan(2, Category::count() > 1 ? count($urls) : 3);
    }

    public function test_public_pages_render_and_links_resolve(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString(route('voting.public.index'), $html);
        $this->assertStringContainsString(route('login'), $html);
        $this->get('/voting')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/up')->assertOk();

        // Every local <img>/<link>/<a> in the landing page points to something that exists.
        preg_match_all('/(?:src|href)="(http:\/\/localhost[^"#?]*)"/', $html, $m);
        foreach (array_unique($m[1]) as $url) {
            $path = parse_url($url, PHP_URL_PATH) ?: '/';
            if (is_file(public_path(ltrim($path, '/')))) {
                continue;
            }
            $this->assertContains($this->get($path)->status(), [200, 302], "broken link $path");
        }
    }

    public function test_every_write_route_answers_with_redirect_or_json(): void
    {
        // PRG audit: no write endpoint may answer 200 with an HTML page.
        $writes = collect(Route::getRoutes())->filter(fn ($r) => array_intersect(['POST', 'PUT', 'DELETE'], $r->methods()));
        $this->assertGreaterThan(25, $writes->count());

        $html200 = [];
        $cat = Category::first();
        $f = Finalist::first();
        $ev = Evaluation::first() ?? Evaluation::create(['judge_id' => $this->judge->id, 'category_id' => $f->category_id, 'finalist_id' => $f->id, 'status' => 'draft']);
        $params = ['category' => $cat->id, 'finalist' => $f->id, 'evaluation' => $ev->id, 'user' => $this->judge->id, 'voter' => EligibleVoter::first()->id];

        // Only non-destructive probes: empty bodies fail validation and must redirect back.
        $skip = ['admin/voting/reset-all', 'admin/finalists/{finalist}', 'admin/presentations/{finalist}', 'logout'];
        foreach ($writes as $r) {
            if (in_array($r->uri(), $skip, true) || str_contains($r->uri(), 'reset') || str_contains($r->uri(), 'toggle')
                || str_contains($r->uri(), 'finalize') || str_contains($r->uri(), 'release') || str_contains($r->uri(), 'republish')
                || str_contains($r->uri(), 'lock') || str_contains($r->uri(), 'reopen') || str_contains($r->uri(), 'settings/') || str_contains($r->uri(), 'winner')) {
                continue;
            }
            $uri = preg_replace_callback('/\{(\w+)\}/', fn ($m) => $params[$m[1]] ?? 1, $r->uri());
            $method = strtolower(collect($r->methods())->first(fn ($m) => in_array($m, ['POST', 'PUT', 'DELETE'])));
            $user = str_starts_with($r->uri(), 'judge') ? $this->judge : $this->admin;
            $res = str_starts_with($r->uri(), 'admin') || str_starts_with($r->uri(), 'judge')
                ? $this->actingAs($user)->{$method}('/'.$uri, [])
                : $this->{$method}('/'.$uri, []);
            $this->app['auth']->forgetGuards();
            if ($res->status() === 200 && str_contains((string) $res->headers->get('Content-Type'), 'text/html')) {
                $html200[] = $r->uri();
            }
        }
        $this->assertSame([], $html200, 'Write routes rendering HTML (breaks PRG): '.implode(', ', $html200));
    }
}
