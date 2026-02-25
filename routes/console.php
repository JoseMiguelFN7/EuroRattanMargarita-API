<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('bcv:fetch')->weekdays()->at('22:00');
