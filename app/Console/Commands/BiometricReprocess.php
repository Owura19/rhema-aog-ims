<?php

namespace App\Console\Commands;

use App\Models\BiometricDevice;
use App\Services\Biometric\PunchProcessor;
use Illuminate\Console\Command;

/**
 * Runs biometric punches that are still waiting (unmatched, no_service) through the processor
 * again: for when a member has since been given a fingerprint ID, or a service has since been
 * generated. Safe to run any number of times. Prints counts only.
 */
class BiometricReprocess extends Command
{
    protected $signature = 'biometric:reprocess {--device= : Only this device id}';

    protected $description = 'Process waiting biometric punches (unmatched, no_service) again';

    public function handle(PunchProcessor $processor): int
    {
        $device = null;
        if ($this->option('device') !== null) {
            $device = BiometricDevice::find($this->option('device'));
            if (! $device) {
                $this->error('No device with that id.');

                return self::FAILURE;
            }
        }

        $r = $processor->reprocessWaiting($device);

        $this->line("Waiting punches checked: {$r['checked']}");
        $this->line("  now on attendance:     {$r['resolved']}");
        $this->line("  still unmatched:       {$r['unmatched']}");
        $this->line("  still without service: {$r['no_service']}");

        return self::SUCCESS;
    }
}
