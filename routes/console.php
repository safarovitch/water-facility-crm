<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
  $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('subscriptions:generate')->everyFiveMinutes();

/*
 * Forecasting maintenance.
 *
 * Order matters within the day: reconcile scores yesterday's forecast and
 * refreshes the bias correction, so it must run before the snapshot that will
 * apply that correction. Seasonality is recomputed weekly rather than nightly
 * because a curve built from months of history cannot move meaningfully in a
 * day, and the recompute walks every order.
 */
Schedule::command('forecast:reconcile')->dailyAt('01:30');
Schedule::command('forecast:snapshot')->dailyAt('02:00');
Schedule::command('forecast:recompute-seasonality')->weeklyOn(1, '03:00');
Schedule::command('forecast:classify-segments')->weeklyOn(1, '03:30');
