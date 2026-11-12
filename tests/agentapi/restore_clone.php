<?php

/**
 * Puts the recording clone (cablebill_oldrec) back to the exact state of the working copy (cablebill02nov) after the old app
 * committed test writes. Only tables the AgentApi writes to are touched: subscriberpayments, subscriberbalance, m_complaints.
 *   php tests/agentapi/restore_clone.php
 */

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$maxPay = (int) DB::selectOne('select max(id) m from cablebill02nov.subscriberpayments')->m;
$maxCmp = (int) DB::selectOne('select max(id) m from cablebill02nov.m_complaints')->m;
$extra = DB::select('select distinct subscriberid from cablebill_oldrec.subscriberpayments where id > ?', [$maxPay]);
$deletedPay = DB::delete('delete from cablebill_oldrec.subscriberpayments where id > ?', [$maxPay]);
$deletedCmp = DB::delete('delete from cablebill_oldrec.m_complaints where id > ?', [$maxCmp]);
$fixed = 0;
foreach ($extra as $r) {
    $id = (int) $r->subscriberid;
    DB::delete('delete from cablebill_oldrec.subscriberbalance where subscriberid = ?', [$id]);
    $fixed += DB::insert('insert into cablebill_oldrec.subscriberbalance select * from cablebill02nov.subscriberbalance where subscriberid = ?', [$id]) ? 1 : 0;
}
$diff = DB::selectOne('select count(*) c from cablebill_oldrec.subscriberbalance a join cablebill02nov.subscriberbalance b on a.subscriberid = b.subscriberid where a.amountdue <> b.amountdue or a.timestamp <> b.timestamp')->c;
echo "clone restored: payments removed $deletedPay, complaints removed $deletedCmp, balances reset for $fixed customer(s); balance rows still different from the copy: $diff\n";
echo 'row counts equal: payments '.(DB::selectOne('select count(*) c from cablebill_oldrec.subscriberpayments')->c === DB::selectOne('select count(*) c from cablebill02nov.subscriberpayments')->c ? 'yes' : 'NO')
    .', complaints '.(DB::selectOne('select count(*) c from cablebill_oldrec.m_complaints')->c === DB::selectOne('select count(*) c from cablebill02nov.m_complaints')->c ? 'yes' : 'NO')."\n";
