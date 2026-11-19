<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('minerva:about', function (): void {
    $this->info('Minerva Digital Platform API — modular monolith');
    $this->line('Modules: Identity, Audit');
});

// Housekeeping: prune expired OAuth state/challenge cache entries are TTL-managed,
// but stale login attempts and accepted invitations can be pruned nightly.
Schedule::command('auth:clear-resets')->daily();
