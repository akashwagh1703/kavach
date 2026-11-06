<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('kavach:scan-missing-punches')->everyFifteenMinutes();
Schedule::command('kavach:scan-document-expiry')->dailyAt('02:00');
Schedule::command('kavach:record-usage')->dailyAt('01:30');
