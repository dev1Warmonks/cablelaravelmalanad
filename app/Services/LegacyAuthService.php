<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Port of Account_model::login(): m_user (status=1) first, then agent (status=1).
 * Same session keys as the CodeIgniter app. Exactly one matching row is required (as before).
 */
class LegacyAuthService
{
    /** Keys the old logout cleared. */
    public const SESSION_KEYS = [
        'username', 'usertype', 'userid', 'name', 'networkname', 'network_id', 'network_name',
        'lcoCode', 'areaname', 'areaid', 'block_network', 'blocknetworkMSG', 'DefaultNotification',
        'agentname',
    ];

    public function attempt(string $username, string $password): bool
    {
        $user = $this->single('m_user', $username, $password);
        if ($user) {
            session([
                'block_network' => '0',
                'userid' => $user->id,
                'name' => $user->username,
                'username' => $user->username,
                'usertype' => $user->type,
                'networkname' => $user->network_name,
            ]);

            if ((int) $user->type !== 1) {
                $network = DB::table('m_network')
                    ->where('status', 1)->where('created_by', $user->id)
                    ->orderBy('id')->first(['network_name', 'id', 'lcoCode']);
                if ($network) {
                    session([
                        'network_id' => $network->id,
                        'network_name' => $network->network_name,
                        'lcoCode' => $network->lcoCode,
                    ]);
                }
            }

            return true;
        }

        $agent = $this->single('agent', $username, $password);
        if ($agent) {
            session([
                'userid' => $agent->id,
                'name' => $agent->username,
                'username' => $agent->username,
                'usertype' => $agent->emptype,
                'agentname' => $agent->name,
            ]);

            $area = DB::table('agent_areas')
                ->leftJoin('m_area', 'm_area.id', '=', 'agent_areas.agent_area_id')
                ->where('agent_areas.agent_id', $agent->id)
                ->orderBy('m_area.id')
                ->first(['m_area.areaname', 'm_area.id']);
            if ($area) {
                session(['areaname' => $area->areaname, 'areaid' => $area->id]);
            }

            return true;
        }

        return false;
    }

    private function single(string $table, string $username, string $password): ?object
    {
        // Username is matched by the DB (same collation behaviour as before); the password is
        // compared in PHP with a timing-safe check. Exactly one match is required.
        $matches = DB::table($table)
            ->where('username', $username)->where('status', 1)
            ->get()
            ->filter(fn ($row) => PasswordVerifier::check($password, $row->password))
            ->values();

        return $matches->count() === 1 ? $matches[0] : null;
    }
}
