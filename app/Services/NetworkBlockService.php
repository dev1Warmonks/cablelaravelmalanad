<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Port of Common_model::block_network() and Common_model::notifications(). */
class NetworkBlockService
{
    /** Copies settings.blocknetworkMSG / DefaultNotification into the session (as the old site did). */
    public function notifications(): void
    {
        foreach (['blocknetworkMSG', 'DefaultNotification'] as $field) {
            $value = DB::table('settings')->where('field', $field)->value('value');
            if ($value !== null) {
                session([$field => $value]);
            }
        }
    }

    /**
     * Returns "true" when the LCO must be sent to the payment screen, else "false"
     * (strings kept because the old code compared against them). Sets session block_network=1 when blocked.
     */
    public function blockNetwork(int|string $userId): string
    {
        $user = DB::table('m_user')->where('id', $userId)->first(['block_network', 'max_pending_invoice']);
        if (! $user || (int) $user->block_network !== 1) {
            return 'false';
        }

        $codes = DB::table('m_network')->where('created_by', $userId)->pluck('lcoCode')->all();
        if (count($codes) === 0) {
            return 'false';
        }

        $pending = DB::table('gsoft_billing')
            ->whereNotIn('bill_status', [2, 4])
            ->whereIn('lco_code', array_merge(['0'], $codes))
            ->count();

        if ($pending > (int) $user->max_pending_invoice) {
            session(['block_network' => '1']);

            return 'true';
        }

        return 'false';
    }
}
