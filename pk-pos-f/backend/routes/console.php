<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Automated SaaS Subscription Lifecycle & Grace Period Lockout Scheduler
Schedule::command('subscriptions:check-lifecycle')
    ->dailyAt('00:05')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

