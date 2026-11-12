<?php

namespace App\Http\Controllers;

use App\Services\AgentApiService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Mobile app API (port of CI AgentApi). JSON body in, JSON out; no session, no cookies, no CSRF (the app authenticates with its
 * static agent token, exactly as before). Output uses plain json_encode so the bytes equal the old app's.
 */
class AgentApiController extends Controller
{
    public function __construct(private readonly AgentApiService $api) {}

    public function login(Request $request): Response
    {
        $key = 'agentapi-login:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 20)) {
            return $this->json(['status' => 'failed', 'message' => 'Too many attempts. Try again later.'], 429);
        }
        $result = $this->run(fn () => $this->api->login($this->body($request)));
        if (($result instanceof Response) || ($result['status'] ?? null) !== 'success') {
            RateLimiter::hit($key, 60);
        } else {
            RateLimiter::clear($key);
        }

        return $result instanceof Response ? $result : $this->json($result);
    }

    public function verify(Request $r): Response
    {
        return $this->handle(fn () => $this->api->verify($this->body($r)));
    }

    public function subscriber_list(Request $r): Response
    {
        return $this->handle(fn () => $this->api->subscriberList($this->body($r)));
    }

    public function transaction_type_list(Request $r): Response
    {
        return $this->handle(fn () => $this->api->transactionTypeList($this->body($r)));
    }

    public function area_list(Request $r): Response
    {
        return $this->handle(fn () => $this->api->areaList($this->body($r)));
    }

    public function print_header(Request $r): Response
    {
        return $this->handle(fn () => $this->api->printHeader($this->body($r)));
    }

    public function add_payment(Request $r): Response
    {
        return $this->handle(fn () => $this->api->addPayment($this->body($r)));
    }

    public function subscriber_transaction_history(Request $r): Response
    {
        return $this->handle(fn () => $this->api->history($this->body($r)));
    }

    public function complaint_type_list(Request $r): Response
    {
        return $this->handle(fn () => $this->api->complaintTypeList($this->body($r)));
    }

    public function complaint_add(Request $r): Response
    {
        return $this->handle(fn () => $this->api->complaintAdd($this->body($r)));
    }

    public function dalily_report(Request $r): Response
    {
        return $this->handle(fn () => $this->api->dailyReport($this->body($r)));
    }

    public function payment_print(Request $r): Response
    {
        $result = $this->run(fn () => $this->api->paymentPrint($this->body($r)));
        if ($result instanceof Response) {
            return $result;
        }
        if ($result === null) {
            return new Response('', 200, ['Content-Type' => 'text/html; charset=UTF-8']);   // old quirk: empty body when the token is missing
        }
        if (is_string($result)) {
            return new Response($result, 200, [
                'Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="doc.pdf"', 'Cache-Control' => 'private, max-age=0, must-revalidate',
            ]);
        }

        return $this->json($result);
    }

    // ------------------------------------------------------------------

    private function handle(callable $fn): Response
    {
        $result = $this->run($fn);

        return $result instanceof Response ? $result : $this->json($result);
    }

    /** Internal errors are logged and answered generically (the old app echoed the exception message). */
    private function run(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (Throwable $e) {
            Log::error('AgentApi error: '.$e->getMessage(), ['exception' => $e::class]);

            return $this->json(['status' => 'failed', 'message' => 'Server error'], 500);
        }
    }

    private function body(Request $request): ?array
    {
        $data = json_decode(trim($request->getContent()), true);

        return is_array($data) ? $data : null;
    }

    /** @param array<string,mixed> $data */
    private function json(array $data, int $status = 200): Response
    {
        return new Response((string) json_encode($data), $status, ['Content-Type' => 'application/json']);
    }
}
