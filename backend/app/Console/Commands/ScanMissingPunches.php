<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Attendance\Services\AttendanceService;
use Illuminate\Console\Command;

class ScanMissingPunches extends Command
{
    protected $signature = 'kavach:scan-missing-punches';

    protected $description = 'Record missing check-ins as exceptions. They are not marked absent.';

    public function handle(AttendanceService $attendance): int
    {
        $created = $attendance->scanMissingPunches();
        $this->info("Missing-punch exceptions recorded: {$created}");

        return self::SUCCESS;
    }
}
