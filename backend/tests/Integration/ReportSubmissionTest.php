<?php

declare(strict_types=1);

namespace Fang\Tests\Integration;

use Fang\Tests\Support\AppHarness;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Email;

final class ReportSubmissionTest extends TestCase
{
    private AppHarness $app;

    protected function setUp(): void
    {
        $this->app = new AppHarness();
    }

    private function storedRowCount(): int
    {
        return (int) $this->app->pdo->query('SELECT COUNT(*) FROM abduction_reports')->fetchColumn();
    }

    public function testFormRendersWithCsrfTokenAndSecurityHeaders(): void
    {
        $response = $this->app->get('/');

        self::assertSame(200, $response->status);
        self::assertStringContainsString('name="csrf_token"', $response->body);
        self::assertStringContainsString("default-src 'none'", $response->headers['Content-Security-Policy']);
        self::assertSame('nosniff', $response->headers['X-Content-Type-Options']);
        self::assertSame('no-store', $response->headers['Cache-Control']);
        self::assertArrayHasKey('Strict-Transport-Security', $response->headers);
    }

    public function testPageLoadsNoThirdPartyAssets(): void
    {
        $body = $this->app->get('/')->body;

        self::assertStringNotContainsString('http://', $body);
        self::assertStringNotContainsString('https://', $body);
        self::assertStringNotContainsString('<script', $body);
    }

    public function testValidSubmissionIsStoredEncrypted(): void
    {
        $response = $this->app->postWithToken('/reports', AppHarness::validReport());

        self::assertSame(303, $response->status);
        self::assertSame('/thanks', $response->headers['Location']);
        self::assertSame(1, $this->storedRowCount());
        $row = $this->app->pdo->query('SELECT * FROM abduction_reports')->fetch();
        $serialized = implode('|', array_map('strval', $row));
        foreach (['Ada', 'Lovelace', 'ada@example.test', 'Small and green', 'Please find the dog'] as $plaintext) {
            self::assertStringNotContainsString($plaintext, $serialized);
        }
    }

    public function testMissingCsrfTokenIsRejected(): void
    {
        $response = $this->app->post('/reports', AppHarness::validReport());

        self::assertSame(403, $response->status);
        self::assertSame(0, $this->storedRowCount());
    }

    public function testWrongCsrfTokenIsRejected(): void
    {
        $this->app->csrfToken();
        $response = $this->app->post('/reports', [...AppHarness::validReport(), 'csrf_token' => str_repeat('0', 64)]);

        self::assertSame(403, $response->status);
        self::assertSame(0, $this->storedRowCount());
    }

    public function testArrayCsrfTokenIsRejected(): void
    {
        $this->app->csrfToken();
        $response = $this->app->post('/reports', [...AppHarness::validReport(), 'csrf_token' => ['x']]);

        self::assertSame(403, $response->status);
    }

    public function testInvalidSubmissionRedisplaysEscapedValues(): void
    {
        $response = $this->app->postWithToken('/reports', AppHarness::validReport([
            'email' => 'bad',
            'comments' => '<script>alert(1)</script>',
        ]));

        self::assertSame(422, $response->status);
        self::assertStringNotContainsString('<script>alert', $response->body);
        self::assertStringContainsString('&lt;script&gt;', $response->body);
        self::assertSame(0, $this->storedRowCount());
    }

    public function testHeaderInjectionNeverReachesTheMailer(): void
    {
        $response = $this->app->postWithToken('/reports', AppHarness::validReport([
            'email' => "ada@example.test\r\nBcc: victim@example.test",
        ]));

        self::assertSame(422, $response->status);
        self::assertSame([], $this->app->mailer->messages);
    }

    public function testNotificationCarriesNoSubmittedData(): void
    {
        $this->app->postWithToken('/reports', AppHarness::validReport());

        self::assertCount(1, $this->app->mailer->messages);
        $message = $this->app->mailer->messages[0];
        self::assertInstanceOf(Email::class, $message);
        $raw = $message->toString();
        foreach (['Ada', 'Lovelace', 'ada@example.test', 'Small and green'] as $plaintext) {
            self::assertStringNotContainsString($plaintext, $raw);
        }
        self::assertSame('from@example.test', $message->getFrom()[0]->getAddress());
        self::assertSame('to@example.test', $message->getTo()[0]->getAddress());
        self::assertSame([], $message->getBcc());
    }

    public function testMailFailureDoesNotLoseTheReport(): void
    {
        $this->app->mailer->fail = true;
        $previousLog = ini_set('error_log', '/dev/null');
        $response = $this->app->postWithToken('/reports', AppHarness::validReport());
        ini_set('error_log', (string) $previousLog);

        self::assertSame(303, $response->status);
        self::assertSame(1, $this->storedRowCount());
    }

    public function testSqlInjectionPayloadIsStoredAsData(): void
    {
        $payload = "x'); DROP TABLE abduction_reports; --";
        $this->app->postWithToken('/reports', AppHarness::validReport(['comments' => $payload]));
        $this->app->signIn();
        $id = (string) $this->app->pdo->query('SELECT public_id FROM abduction_reports')->fetchColumn();

        self::assertSame(1, $this->storedRowCount());
        self::assertStringContainsString(htmlspecialchars($payload, ENT_QUOTES | ENT_HTML5), $this->app->get('/admin/reports/' . $id)->body);
    }

    public function testSubmissionsAreRateLimitedPerClient(): void
    {
        for ($attempt = 0; $attempt < 10; ++$attempt) {
            self::assertSame(303, $this->app->postWithToken('/reports', AppHarness::validReport())->status);
        }
        $blocked = $this->app->postWithToken('/reports', AppHarness::validReport());

        self::assertSame(429, $blocked->status);
        self::assertSame('3600', $blocked->headers['Retry-After']);
        self::assertSame(303, $this->app->postWithToken('/reports', AppHarness::validReport(), '203.0.113.99')->status);
    }

    public function testRotatingWithinAnIpv6PrefixDoesNotEvadeTheLimit(): void
    {
        $token = $this->app->csrfToken();
        for ($host = 1; $host <= 10; ++$host) {
            $accepted = $this->app->post('/reports', [...AppHarness::validReport(), 'csrf_token' => $token], '2001:db8:1:1::' . dechex($host));
            self::assertSame(303, $accepted->status);
        }

        $blocked = $this->app->post('/reports', [...AppHarness::validReport(), 'csrf_token' => $token], '2001:db8:1:1:ffff::1');

        self::assertSame(429, $blocked->status);
    }

    public function testOversizedBodyIsRejected(): void
    {
        $response = $this->app->kernel->handle(new \Fang\Http\Request('POST', '/reports', [], '203.0.113.10', 40000));

        self::assertSame(413, $response->status);
    }

    public function testUnknownRouteAndMethodAreHandled(): void
    {
        self::assertSame(404, $this->app->get('/report.php')->status);
        self::assertSame(405, $this->app->kernel->handle(new \Fang\Http\Request('PUT', '/'))->status);
    }
}
