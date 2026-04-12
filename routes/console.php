<?php

declare(strict_types=1);

use App\Console\Commands\DispatchApprovalEscalations;
use Illuminate\Support\Facades\Schedule;

Schedule::command(DispatchApprovalEscalations::class)
    ->everyTenMinutes()
    ->withoutOverlapping();
