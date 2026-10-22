<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('invoices:mark-overdue')
    ->dailyAt('00:05')
    ->onOneServer()
    ->withoutOverlapping(30);

Schedule::command('invoices:generate-monthly')
    ->dailyAt('00:10')
    ->onOneServer()
    ->withoutOverlapping(60);

Schedule::command('mikrotik:sync-all-pppoe')
    ->everyFiveMinutes()
    ->onOneServer()
    ->withoutOverlapping(10);

Schedule::command('genieacs:sync-onts')
    ->everyTwoMinutes()
    ->onOneServer()
    ->withoutOverlapping(10);

Schedule::command('genieacs:scan-alerts')
    ->everyFiveMinutes()
    ->onOneServer()
    ->withoutOverlapping(10);

Schedule::command('genieacs:prune-history')
    ->dailyAt('03:30')
    ->onOneServer()
    ->withoutOverlapping(60);

Schedule::command('billing:auto-isolate')
    ->everyFiveMinutes()
    ->onOneServer()
    ->withoutOverlapping(10);

Schedule::command('billing:retry-pending-restores')
    ->everyTwoMinutes()
    ->onOneServer()
    ->withoutOverlapping(10);

Schedule::command('asnet:backup')
    ->dailyAt('02:00')
    ->onOneServer()
    ->withoutOverlapping(120);

Schedule::command('asnet:health-check')
    ->everyFiveMinutes()
    ->onOneServer()
    ->withoutOverlapping(10);

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
