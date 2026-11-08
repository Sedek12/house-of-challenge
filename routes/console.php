<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('hoc:maintenance')->everyFiveMinutes()->withoutOverlapping();
