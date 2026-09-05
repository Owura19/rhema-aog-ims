<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Pledge;
use App\Models\Member;
use Illuminate\Support\Facades\DB;

class PledgePaymentImport extends Command
{
    protected $signature = 'pledgepay:import {file} {--purpose=2}';
    protected $description = 'Import pledge payments; create pledges for new givers';

    public function handle()
    {
        $path = $this->argument('file');
        $purpose = (int)$this->option('purpose');
        if (!is_readable($path)) { $this->error("Cannot read {$path}"); return 1; }
        $lines = array_filter(array_map('trim', file($path)));

        $titles = ['MR','MRS','MISS','MAD','MADAM','DEAC','DEACON','DEA','NANA','HON','REV','DR','PROF'];
        $norm = function($s) use ($titles) {
            $s = strtoupper(str_replace(['&','.',"'"], ' ', $s));
            $s = preg_replace('/\([^)]*\)/', '', $s);
            $parts = array_filter(preg_split('/\s+/', $s), fn($w) => $w && !in_array($w,$titles) && !in_array($w,['FAMILY','WIFE','THE','JNR','SNR']) && strlen($w)>1);
            sort($parts);
            return implode(' ', $parts);
        };

        // index existing pledges by normalized name
        $pledgeIdx = [];
        foreach (Pledge::whereNull('deleted_at')->get() as $pl) {
            if ($pl->member_id) {
                $m = Member::find($pl->member_id);
                $nm = $m ? trim($m->first_name.' '.$m->last_name) : '';
            } else { $nm = $pl->pledger_name ?? ''; }
            if ($nm) $pledgeIdx[$norm($nm)][] = $pl->id;
        }

        // group lines by payer for new-pledge creation
        $newGivers = []; // payer => [total, [payments]]
        $matchedPayments = []; // [pledge_id, amount, date]

        foreach ($lines as $ln) {
            $p = explode('|', $ln);
            if (count($p) < 5) continue;
            [$tag,$payer,$pledgeName,$amt,$date] = $p;
            $amt = (float)$amt; $payer = trim($payer);
            $date = trim($date) ?: now()->toDateString();
            if ($tag === 'M') {
                $key = $norm($pledgeName);
                if (isset($pledgeIdx[$key])) {
                    $matchedPayments[] = [$pledgeIdx[$key][0], $amt, $date];
                }
            } else {
                $newGivers[$payer]['total'] = ($newGivers[$payer]['total'] ?? 0) + $amt;
                $newGivers[$payer]['pmts'][] = [$amt, $date];
            }
        }

        $lastId = Pledge::withTrashed()->max('id') ?? 0;
        $createdPledges = 0; $paymentsMade = 0;

        DB::beginTransaction();
        try {
            // 1) matched payments
            foreach ($matchedPayments as [$pid,$amt,$date]) {
                DB::table('pledge_payments')->insert([
                    'pledge_id'=>$pid,'amount'=>$amt,'payment_date'=>$date,
                    'payment_method'=>'Cash','transaction_id'=>null,
                    'notes'=>'Imported harvest payment','recorded_by'=>1,
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
                $paymentsMade++;
            }
            // 2) new givers: create pledge (pledged = total paid, status Fulfilled) + payments
            foreach ($newGivers as $payer=>$info) {
                $lastId++;
                $ref = 'PLG-'.str_pad($lastId,5,'0',STR_PAD_LEFT);
                $pid = DB::table('pledges')->insertGetId([
                    'reference'=>$ref,'member_id'=>null,'pledger_name'=>$payer,
                    'pledge_purpose_id'=>$purpose,'amount_pledged'=>$info['total'],
                    'currency'=>'GHS','date_pledged'=>now()->toDateString(),
                    'status'=>'Fulfilled','notes'=>'Harvest giver (imported)','recorded_by'=>1,
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
                $createdPledges++;
                foreach ($info['pmts'] as [$amt,$date]) {
                    DB::table('pledge_payments')->insert([
                        'pledge_id'=>$pid,'amount'=>$amt,'payment_date'=>$date,
                        'payment_method'=>'Cash','transaction_id'=>null,
                        'notes'=>'Imported harvest payment','recorded_by'=>1,
                        'created_at'=>now(),'updated_at'=>now(),
                    ]);
                    $paymentsMade++;
                }
            }
            DB::commit();
            $this->info("=== IMPORT COMPLETE ===");
            $this->line("New pledges created: {$createdPledges}");
            $this->line("Total payments recorded: {$paymentsMade}");
            return 0;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("ROLLED BACK: ".$e->getMessage());
            return 1;
        }
    }
}
