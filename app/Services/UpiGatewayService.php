<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * UPIGateway HTTP client (create_order / check_order_status).
 *
 * The key comes from config (.env UPIGATEWAY_KEY) and is never logged. Tests MUST fake the HTTP client
 * (Http::fake) and never use a live key; with no key configured every call fails safely without any network access.
 */
class UpiGatewayService
{
    public function configured(): bool
    {
        return (string) config('cablebill.upigateway.key') !== '';
    }

    private function post(string $path, array $payload): array
    {
        if (! $this->configured()) {
            return ['status' => false, 'msg' => 'Gateway not configured'];
        }
        try {
            $response = Http::acceptJson()->asJson()->timeout((int) config('cablebill.upigateway.timeout'))
                ->post(rtrim((string) config('cablebill.upigateway.base_url'), '/').'/'.$path, ['key' => config('cablebill.upigateway.key')] + $payload);
            $json = $response->json();

            return is_array($json) ? $json : ['status' => false, 'msg' => 'Invalid gateway response'];
        } catch (\Throwable $e) {
            Log::error('upigateway request failed', ['path' => $path, 'error' => $e->getMessage()]);

            return ['status' => false, 'msg' => 'Gateway unreachable'];
        }
    }

    /** @return array{status:bool, msg?:string, data?:array} */
    public function createOrder(string $clientTxnId, string $amount, string $customerName, string $customerMobile): array
    {
        return $this->post('create_order', [
            'client_txn_id' => $clientTxnId,
            'amount' => $amount,
            'p_info' => 'GSoft Monthly bill',
            'customer_name' => $customerName,
            'customer_email' => (string) config('cablebill.upigateway.customer_email'),
            'customer_mobile' => $customerMobile,
            'redirect_url' => (string) config('cablebill.upigateway.redirect_url'),
        ]);
    }

    /** @param string $txnDateDmy date in d-m-Y as the gateway expects */
    public function checkOrderStatus(string $clientTxnId, string $txnDateDmy): array
    {
        return $this->post('check_order_status', ['client_txn_id' => $clientTxnId, 'txn_date' => $txnDateDmy]);
    }
}
