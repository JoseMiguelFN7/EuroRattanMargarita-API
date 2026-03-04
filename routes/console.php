<?php

use Illuminate\Support\Facades\Schedule;
use App\Jobs\FetchBcvRateJob;

Schedule::command('backup:clean')->dailyAt('01:00');
Schedule::command('backup:run')->dailyAt('02:00')->runInBackground();
Schedule::command('chat:clean-temp')->weeklyOn(0, '03:00');
Schedule::job(new FetchBcvRateJob)->weekdays()->at('22:00');
