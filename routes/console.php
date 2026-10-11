<?php

use App\Console\Commands\CheckAgentHealthCommand;
use App\Console\Commands\ReconcileHitlApprovalsCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(CheckAgentHealthCommand::class)->everyMinute();
Schedule::command(ReconcileHitlApprovalsCommand::class)->everyMinute();
