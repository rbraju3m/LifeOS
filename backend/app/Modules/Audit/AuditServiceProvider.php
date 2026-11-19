<?php

namespace App\Modules\Audit;

use App\Modules\Audit\Console\PruneAuditLogs;
use App\Modules\Audit\Listeners\RecordAuthEvents;
use App\Modules\Shared\Providers\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;

class AuditServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        Event::subscribe(RecordAuthEvents::class);

        if ($this->app->runningInConsole()) {
            $this->commands([PruneAuditLogs::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('audit:prune')->monthlyOn(1, '03:30')->onOneServer()->withoutOverlapping();
        });
    }
}
