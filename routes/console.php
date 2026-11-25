<?php

use Illuminate\Support\Facades\Schedule;

if (! config('queue.worker_supervisionado')) {
    Schedule::command('queue:work --timeout=600')->everyMinute()->withoutOverlapping();
}

Schedule::exec('sh '.base_path('docker/backup-sqlite.sh'))
    ->dailyAt('06:00')
    ->when(fn () => config('database.default') === 'sqlite' && is_file('/data/database.sqlite'));
