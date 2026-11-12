<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
| Every Feature test runs against the local DB copy inside a transaction that is rolled back (DatabaseTransactions).
| MyISAM tables (m_user, settings, expenses, upigateway_history, ...) cannot be rolled back: tests must not write to them
| (or must clean up by hand).
*/
pest()->extend(TestCase::class)->use(DatabaseTransactions::class)->in('Feature');

// ---------------------------------------------------------------- fixtures picked from the data

function lcoUser(): object
{
    static $u;

    return $u ??= DB::selectOne("select u.id, u.username, u.password, n.lcoCode, n.id nid from m_user u join m_network n on n.created_by = u.id
        where u.type = 2 and u.status = 1 and n.status = 1 and u.username = 'shanavas' limit 1")
        ?? DB::selectOne('select u.id, u.username, u.password, n.lcoCode, n.id nid from m_user u join m_network n on n.created_by = u.id where u.type = 2 and u.status = 1 and n.status = 1 limit 1');
}

function otherLcoUser(): object
{
    static $u;

    return $u ??= DB::selectOne('select u.id, u.username, u.password, n.lcoCode, n.id nid from m_user u join m_network n on n.created_by = u.id
        where u.type = 2 and u.status = 1 and n.status = 1 and u.id <> ? limit 1', [lcoUser()->id]);
}

function adminUser(): object
{
    static $u;

    return $u ??= DB::selectOne('select id, username, password from m_user where type = 1 and status = 1 limit 1');
}

function agentUser(): object
{
    static $u;

    return $u ??= DB::selectOne("select a.id, a.username, a.password, a.token, a.created_by from agent a where a.status = 1 and a.emptype = 3 and a.username <> '' and a.password <> ''
        and a.token <> '' and exists (select 1 from m_user m where m.id = a.created_by and m.status = 1) and exists (select 1 from m_network n where n.created_by = a.created_by) order by a.id desc limit 1");
}

/** A customer of the given LCO / of any other LCO. */
function customerOf(string $lco): object
{
    return DB::table('m_customer')->where('lcoCode', $lco)->where('status', '!=', 2)->orderBy('id')->first();
}

function customerNotOf(string $lco): object
{
    return DB::table('m_customer')->where('lcoCode', '<>', $lco)->where('status', '!=', 2)->orderBy('id')->first();
}

/** Logs in through the real login route (so the real session keys are set). */
function loginAs(object $user): void
{
    test()->flushSession();
    test()->post('login/process_login', ['username' => $user->username, 'password' => $user->password]);
}
