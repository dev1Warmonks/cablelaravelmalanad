<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Port of the per-controller menu_list() / network_list() helpers (m_main_menus / m_user_roles). */
class MenuService
{
    /** Menu ids the current user may see: all for usertype 1, else rows in m_user_roles. */
    public function menuList(): array
    {
        if ((string) session('usertype') === '1') {
            return DB::table('m_main_menus')->pluck('menu_id')->all();
        }

        return DB::table('m_user_roles')->where('user_id', session('userid'))->pluck('menu_id')->all();
    }

    /** Networks owned by the logged-in user (active). */
    public function networkList(): array
    {
        return DB::table('m_network')
            ->where('created_by', session('userid'))->where('status', 1)
            ->get(['network_name', 'id', 'lcoCode'])
            ->map(fn ($r) => (array) $r)->all();
    }
}
