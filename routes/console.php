<?php

use Illuminate\Support\Facades\Schedule;
use App\Jobs\FetchBcvRateJob;

Schedule::job(new FetchBcvRateJob)->weekdays()->at('22:00');
