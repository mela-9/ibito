<?php

use App\Services\CirculationService;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function (CirculationService $circulation) {
    $circulation->processAutomaticReturns();
})->name('automatic-library-circulation')->everyFifteenMinutes()->withoutOverlapping();
