<?php

declare(strict_types=1);

namespace Fang\Http;

use Fang\Http\Controller\AdminController;
use Fang\Http\Controller\ReportController;
use Fang\Security\CsrfGuard;
use Throwable;

final readonly class Kernel
{
    private const int MAX_BODY_BYTES = 32768;
    private const string REPORT_PATH = '#\A/admin/reports/([a-f0-9]{32})(/delete)?\z#';

    public function __construct(
        private ReportController $reports,
        private AdminController $admin,
        private CsrfGuard $csrf,
        private Renderer $renderer,
        private bool $secure,
    ) {
    }

    public function handle(Request $request): Response
    {
        try {
            $response = $this->dispatch($request);
        } catch (Throwable $exception) {
            error_log('Fang: unhandled ' . $exception::class . ' at ' . basename($exception->getFile()) . ':' . $exception->getLine());
            $response = $this->error(500, 'Something went wrong.');
        }

        return $response->withHeaders(SecurityHeaders::for($this->secure));
    }

    private function dispatch(Request $request): Response
    {
        if ($request->contentLength > self::MAX_BODY_BYTES) {
            return $this->error(413, 'The submitted form is too large.');
        }
        $method = $request->method === 'HEAD' ? 'GET' : $request->method;
        if (!in_array($method, ['GET', 'POST'], true)) {
            return $this->error(405, 'Method not allowed.')->withHeaders(['Allow' => 'GET, HEAD, POST']);
        }
        if ($method === 'POST' && !$this->csrf->isValid($request->post['csrf_token'] ?? null)) {
            return $this->error(403, 'Your session expired. Reload the page and try again.');
        }
        if (preg_match(self::REPORT_PATH, $request->path, $matches) === 1) {
            $isDelete = ($matches[2] ?? '') !== '';
            return match (true) {
                $isDelete && $method === 'POST' => $this->admin->deleteReport($matches[1]),
                !$isDelete && $method === 'GET' => $this->admin->showReport($matches[1]),
                default => $this->error(405, 'Method not allowed.'),
            };
        }

        return match ($method . ' ' . $request->path) {
            'GET /' => $this->reports->showForm(),
            'POST /reports' => $this->reports->submit($request),
            'GET /thanks' => $this->reports->thanks(),
            'GET /admin' => $this->admin->dashboard(),
            'GET /admin/login' => $this->admin->showLogin(),
            'POST /admin/login' => $this->admin->login($request),
            'GET /admin/two-factor' => $this->admin->showTwoFactor(),
            'POST /admin/two-factor' => $this->admin->verifyTwoFactor($request),
            'POST /admin/logout' => $this->admin->logout(),
            default => $this->error(404, 'Page not found.'),
        };
    }

    private function error(int $status, string $message): Response
    {
        return Response::html($this->renderer->page('error', 'Error', ['message' => $message]), $status);
    }
}
