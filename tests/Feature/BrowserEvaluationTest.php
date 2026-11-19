<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Real browser end-to-end test of the evaluation page.
 *
 * A throw-away SQLite database is migrated and seeded, a PHP development
 * server is started for it and a head-less Chrome instance (via the DevTools
 * protocol) performs the real user interaction:
 *
 *   - clicking a score button updates "Selected", the criterion contribution,
 *     the weighted total preview and the criteria counter IMMEDIATELY, before
 *     the network request completes
 *   - the autosave request is sent to the correct endpoint and returns 200
 *   - the values are persisted in the database
 *   - reopening the evaluation loads the persisted values
 *
 * The test is skipped when no Chrome/Chromium/Edge binary is available.
 */
class BrowserEvaluationTest extends TestCase
{
    private ?Process $server = null;

    private ?string $workspace = null;

    private function chromeBinary(): ?string
    {
        $candidates = [
            'C:/Program Files/Google/Chrome/Application/chrome.exe',
            'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe',
            'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
            'C:/Program Files/Microsoft/Edge/Application/msedge.exe',
            '/usr/bin/google-chrome',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function serverEnvironment(string $database): array
    {
        // The full parent environment is inherited and only the values below
        // are overridden (Windows needs SystemRoot, PATH, ... to be present).
        return array_merge(getenv(), [
            'APP_ENV' => 'testing',
            'APP_KEY' => (string) config('app.key'),
            'APP_DEBUG' => 'true',
            'APP_URL' => 'http://127.0.0.1:'.$this->port(),
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $database,
            'SESSION_DRIVER' => 'file',
            'CACHE_STORE' => 'file',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'array',
            'FILESYSTEM_DISK' => 'local',
            'BCRYPT_ROUNDS' => '4',
        ]);
    }

    private function port(): int
    {
        if ($this->port === null) {
            $this->port = random_int(8500, 8900);
        }

        return $this->port;
    }

    private ?int $port = null;

    private function runArtisan(array $arguments, string $database): void
    {
        $process = new Process(
            array_merge([PHP_BINARY, 'artisan'], $arguments),
            base_path(),
            $this->serverEnvironment($database)
        );
        $process->run();

        $this->assertTrue(
            $process->isSuccessful(),
            'artisan '.implode(' ', $arguments).' failed: '.$process->getErrorOutput().$process->getOutput()
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->chromeBinary()) {
            $this->markTestSkipped('No Chrome/Chromium/Edge binary found - the browser test cannot run.');
        }

        $this->workspace = sys_get_temp_dir().DIRECTORY_SEPARATOR.'jc-browser-test-'.uniqid();
        mkdir($this->workspace, 0777, true);
    }

    protected function tearDown(): void
    {
        if ($this->server) {
            $this->server->stop(2);
            $this->server = null;
        }

        if ($this->workspace && is_dir($this->workspace)) {
            @array_map('unlink', glob($this->workspace.DIRECTORY_SEPARATOR.'*') ?: []);
            @rmdir($this->workspace);
        }

        parent::tearDown();
    }

    public function test_the_evaluation_page_saves_and_reloads_in_a_real_browser(): void
    {
        $database = $this->workspace.DIRECTORY_SEPARATOR.'testing.sqlite';
        touch($database);

        // 1. migrate + seed the throw-away database
        $this->runArtisan(['migrate', '--force'], $database);
        $this->runArtisan(['tinker', '--execute', "include base_path('tests/Support/browser_fixture.php');"], $database);

        // 2. start a development server for it
        $this->server = new Process(
            [PHP_BINARY, 'artisan', 'serve', '--host=127.0.0.1', '--port='.$this->port()],
            base_path(),
            $this->serverEnvironment($database)
        );
        $this->server->start();

        $baseUrl = 'http://127.0.0.1:'.$this->port();

        $ready = false;
        for ($i = 0; $i < 60; $i++) {
            $response = @file_get_contents($baseUrl.'/up');
            if ($response !== false) {
                $ready = true;
                break;
            }
            usleep(250000);
        }
        $this->assertTrue($ready, 'The development server did not start on '.$baseUrl);

        // 3. drive a real browser
        $process = new Process(
            ['node', base_path('tests/Support/browser-evaluate.test.mjs'), $baseUrl, 'browserjudge', 'BrowserTest123!'],
            base_path(),
            $this->serverEnvironment($database)
        );
        $process->run();
        $output = $process->getOutput();

        $this->assertTrue($process->isSuccessful(), 'The browser script failed: '.$output.$process->getErrorOutput());

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded, 'Unexpected browser output: '.$output);
        $this->assertTrue($decoded['ok'] ?? false, 'The browser script reported a failure: '.$output);

        $result = $decoded['result'];

        // No JavaScript error may occur (this is what broke the page before).
        $this->assertSame([], $result['exceptions'], 'The page raised JavaScript exceptions: '.implode(' | ', $result['exceptions']));

        // Nothing is selected before the first click.
        $this->assertSame('0', $result['before']['count']);
        $this->assertSame('0.00', $result['before']['total']);
        $this->assertSame('-,-,-,-,-,-', $result['before']['selected']);

        // The summary updates IMMEDIATELY, before the request completes.
        $this->assertSame('6', $result['immediately']['count']);
        $this->assertSame('80.00', $result['immediately']['total']);
        $this->assertSame('4,4,4,4,4,4', $result['immediately']['selected']);
        $this->assertSame('8.00,8.00,16.00,16.00,12.00,20.00', $result['immediately']['contributions']);
        $this->assertSame($result['immediately']['contributions'], $result['immediately']['summaryContributions']);

        // The autosave request was sent to the right endpoint and succeeded.
        $request = collect($result['autosaves'])->firstWhere('url') ?? [];
        $response = collect($result['autosaves'])->firstWhere('status') ?? [];
        $this->assertNotEmpty($request, 'No autosave request was sent.');
        $this->assertSame('POST', $request['method']);
        $this->assertStringEndsWith('/autosave', $request['url']);
        $this->assertSame(200, $response['status'] ?? 0, 'The autosave request did not return HTTP 200.');
        $this->assertStringContainsString('"scores"', (string) $request['body']);

        // The status is reported as saved.
        $this->assertStringStartsWith('Saved', $result['afterSave']['status']);

        // The values are restored from the database after a reload.
        $this->assertSame('6', $result['afterReload']['count']);
        $this->assertSame('80.00', $result['afterReload']['total']);
        $this->assertSame('4,4,4,4,4,4', $result['afterReload']['selected']);
        $this->assertSame('8.00,8.00,16.00,16.00,12.00,20.00', $result['afterReload']['contributions']);
    }

    public function test_the_persisted_scores_are_stored_in_the_database(): void
    {
        $database = $this->workspace.DIRECTORY_SEPARATOR.'testing.sqlite';
        touch($database);

        $this->runArtisan(['migrate', '--force'], $database);
        $this->runArtisan(['tinker', '--execute', "include base_path('tests/Support/browser_fixture.php');"], $database);

        $this->server = new Process(
            [PHP_BINARY, 'artisan', 'serve', '--host=127.0.0.1', '--port='.$this->port()],
            base_path(),
            $this->serverEnvironment($database)
        );
        $this->server->start();

        $baseUrl = 'http://127.0.0.1:'.$this->port();
        for ($i = 0; $i < 60; $i++) {
            if (@file_get_contents($baseUrl.'/up') !== false) {
                break;
            }
            usleep(250000);
        }

        $process = new Process(
            ['node', base_path('tests/Support/browser-evaluate.test.mjs'), $baseUrl, 'browserjudge', 'BrowserTest123!'],
            base_path(),
            $this->serverEnvironment($database)
        );
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getOutput().$process->getErrorOutput());

        // Read the persisted draft straight from the throw-away database.
        $dump = new Process(
            [PHP_BINARY, 'artisan', 'tinker', '--execute', "echo App\\Models\\Evaluation::with('scores')->get()->map(fn(\$e) => \$e->scores->pluck('score')->implode(',').'|'.\$e->total_score)->implode(';');"],
            base_path(),
            $this->serverEnvironment($database)
        );
        $dump->run();
        $output = trim($dump->getOutput());

        $this->assertStringContainsString('4,4,4,4,4,4', $output, 'The scores were not persisted: '.$output);
        $this->assertStringContainsString('|80', $output, 'The weighted total was not persisted: '.$output);
    }
}
