<?php

use Illuminate\Support\Facades\Schedule;
use App\Jobs\FetchBcvRateJob;

Schedule::command('backup:clean')->dailyAt('01:00');
Schedule::command('backup:run')->dailyAt('02:00')->runInBackground();
Schedule::job(new FetchBcvRateJob)->weekdays()->at('22:00');
