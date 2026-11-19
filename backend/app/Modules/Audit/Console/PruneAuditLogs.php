<?php

namespace App\Modules\Audit\Console;

use App\Modules\Audit\AuditCategory;
use App\Modules\Audit\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;

/**
 * Deletes audit entries past their retention (docs/blueprint/10 §10.8):
 * security events after 2 years, everything else after 1 year. This is the
 * only code path that deletes audit rows; it bypasses the model's append-only
 * guard on purpose by deleting through the query builder.
 */
class PruneAuditLogs extends Command
{
    protected $signature = 'audit:prune {--chunk=1000 : Rows deleted per statement}';

    protected $description = 'Delete audit log entries older than their retention period';

    public function handle(): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $securityCutoff = now()->subDays((int) config('lifeos.audit.retention_days.security'));
        $otherCutoff = now()->subDays((int) config('lifeos.audit.retention_days.other'));
        $prefix = AuditCategory::SECURITY_PREFIX.'%';

        $security = $this->deleteInChunks(fn () => AuditLog::query()->toBase()
            ->where('action', 'like', $prefix)
            ->where('created_at', '<', $securityCutoff), $chunk);

        $other = $this->deleteInChunks(fn () => AuditLog::query()->toBase()
            ->where('action', 'not like', $prefix)
            ->where('created_at', '<', $otherCutoff), $chunk);

        $this->info("Pruned {$security} security and {$other} other audit entries.");

        return self::SUCCESS;
    }

    /**
     * @param  callable(): Builder  $query
     */
    private function deleteInChunks(callable $query, int $chunk): int
    {
        $total = 0;

        do {
            $deleted = $query()->orderBy('created_at')->limit($chunk)->delete();
            $total += $deleted;
        } while ($deleted === $chunk);

        return $total;
    }
}
