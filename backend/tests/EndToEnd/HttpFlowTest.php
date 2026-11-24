<?php

declare(strict_types=1);

namespace Fang\Tests\EndToEnd;

use Fang\Application;
use OTPHP\TOTP;
use PHPUnit\Framework\TestCase;

final class HttpFlowTest extends TestCase
{
    private const string PASSWORD = 'end to end test passphrase';

    private string $directory;
    private array $environment;
    private mixed $server = null;
    private int $port = 0;
    private array $cookies = [];

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/fang-e2e-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700);
        $this->environment = [
            'PATH' => (string) getenv('PATH'),
            'FANG_MASTER_KEY' => base64_encode(sodium_crypto_kdf_keygen()),
            'FANG_DB_PATH' => $this->directory . '/fang.sqlite',
            'FANG_MAILER_DSN' => 'null://null',
            'FANG_MAIL_FROM' => 'from@example.test',
            'FANG_MAIL_TO' => 'to@example.test',
            'FANG_COOKIE_SECURE' => 'false',
        ];
        $probe = stream_socket_server('tcp://127.0.0.1:0');
        self::assertIsResource($probe);
        $this->port = (int) substr((string) strrchr((string) stream_socket_get_name($probe, false), ':'), 1);
        fclose($probe);
        $this->runCli(['db:migrate']);
        $root = realpath(Application::ROOT);
        $this->server = proc_open(
            [PHP_BINARY, '-d', 'session.save_path=' . $this->directory, '-S', '127.0.0.1:' . $this->port, '-t', $root . '/frontend/public', $root . '/frontend/public/index.php'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
            $root,
            $this->environment,
        );
        for ($attempt = 0; $attempt < 100; ++$attempt) {
            $socket = @fsockopen('127.0.0.1', $this->port, $errorCode, $errorMessage, 0.1);
            if (is_resource($socket)) {
                fclose($socket);

                return;
            }
            usleep(50000);
        }
        self::fail('Test server did not start.');
    }

    protected function tearDown(): void
    {
        if (is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
        }
        foreach ((array) glob($this->directory . '/*') as $file) {
            @unlink((string) $file);
        }
        @rmdir($this->directory);
    }

    private function runCli(array $arguments, string $input = ''): string
    {
        $process = proc_open(
            [PHP_BINARY, realpath(Application::ROOT) . '/backend/bin/fang', ...$arguments],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
            null,
            $this->environment,
        );
        self::assertIsResource($process);
        fwrite($pipes[0], $input);
        fclose($pipes[0]);
        $output = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        self::assertSame(0, proc_close($process));

        return $output;
    }

    private function request(string $method, string $path, array $fields = []): array
    {
        $headers = [];
        if ($this->cookies !== []) {
            $pairs = [];
            foreach ($this->cookies as $name => $value) {
                $pairs[] = $name . '=' . $value;
            }
            $headers[] = 'Cookie: ' . implode('; ', $pairs);
        }
        $options = ['method' => $method, 'ignore_errors' => true, 'follow_location' => 0];
        if ($method === 'POST') {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $options['content'] = http_build_query($fields);
        }
        $options['header'] = $headers;
        $body = (string) file_get_contents('http://127.0.0.1:' . $this->port . $path, false, stream_context_create(['http' => $options]));
        $responseHeaders = http_get_last_response_headers() ?? [];
        http_clear_last_response_headers();
        preg_match('#\AHTTP/\S+ (\d{3})#', (string) ($responseHeaders[0] ?? ''), $status);
        foreach ($responseHeaders as $header) {
            if (preg_match('/\ASet-Cookie: ([^=]+)=([^;]*)/i', (string) $header, $cookie) === 1) {
                if ($cookie[2] === 'deleted' || $cookie[2] === '') {
                    unset($this->cookies[$cookie[1]]);
                } else {
                    $this->cookies[$cookie[1]] = $cookie[2];
                }
            }
        }

        return ['status' => (int) ($status[1] ?? 0), 'headers' => $responseHeaders, 'body' => $body];
    }

    private function tokenFrom(string $body): string
    {
        self::assertSame(1, preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $body, $matches));

        return $matches[1];
    }

    public function testCompleteSubmissionAndAdminJourney(): void
    {
        $uri = trim($this->runCli(['admin:create', 'site-admin'], self::PASSWORD . "\n"));
        parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);
        $secret = (string) $query['secret'];

        $form = $this->request('GET', '/');
        self::assertSame(200, $form['status']);
        $cookieHeader = implode("\n", preg_grep('/^Set-Cookie:/i', $form['headers']) ?: []);
        self::assertStringContainsStringIgnoringCase('HttpOnly', $cookieHeader);
        self::assertStringContainsStringIgnoringCase('SameSite=Strict', $cookieHeader);

        self::assertSame(403, $this->request('POST', '/reports', ['first_name' => 'Ada'])['status']);

        $submitted = $this->request('POST', '/reports', [
            'csrf_token' => $this->tokenFrom($form['body']),
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.test',
            'occurred_when' => 'Last Tuesday',
            'fang_spotted' => 'no',
            'comments' => '<script>alert(1)</script>',
        ]);
        self::assertSame(303, $submitted['status']);

        $database = (string) file_get_contents($this->directory . '/fang.sqlite') . (string) @file_get_contents($this->directory . '/fang.sqlite-wal');
        self::assertStringNotContainsString('Lovelace', $database);
        self::assertStringNotContainsString('ada@example.test', $database);

        self::assertSame(303, $this->request('GET', '/admin')['status']);

        $login = $this->request('GET', '/admin/login');
        $afterPassword = $this->request('POST', '/admin/login', [
            'csrf_token' => $this->tokenFrom($login['body']),
            'username' => 'site-admin',
            'password' => self::PASSWORD,
        ]);
        self::assertSame(303, $afterPassword['status']);
        self::assertSame(303, $this->request('GET', '/admin')['status']);

        $challenge = $this->request('GET', '/admin/two-factor');
        $verified = $this->request('POST', '/admin/two-factor', [
            'csrf_token' => $this->tokenFrom($challenge['body']),
            'code' => TOTP::createFromSecret($secret)->at(time()),
        ]);
        self::assertSame(303, $verified['status']);

        $dashboard = $this->request('GET', '/admin');
        self::assertSame(200, $dashboard['status']);
        self::assertStringContainsString('Lovelace', $dashboard['body']);
        self::assertSame(1, preg_match('#/admin/reports/([a-f0-9]{32})#', $dashboard['body'], $link));

        $detail = $this->request('GET', '/admin/reports/' . $link[1]);
        self::assertStringContainsString('&lt;script&gt;', $detail['body']);
        self::assertStringNotContainsString('<script>alert', $detail['body']);

        $loggedOut = $this->request('POST', '/admin/logout', ['csrf_token' => $this->tokenFrom($dashboard['body'])]);
        self::assertSame(303, $loggedOut['status']);
        self::assertSame(303, $this->request('GET', '/admin')['status']);
    }

    public function testStylesheetIsServedWithoutReachingTheApplication(): void
    {
        $response = $this->request('GET', '/assets/app.css');

        self::assertSame(200, $response['status']);
        self::assertStringContainsString('--accent', $response['body']);
    }

    public function testBackendSourceIsNotReachableOverHttp(): void
    {
        foreach (['/backend/src/Application.php', '/.env', '/db/schema.sql', '/composer.json'] as $path) {
            self::assertSame(404, $this->request('GET', $path)['status'], $path);
        }
    }
}
