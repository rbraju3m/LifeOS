<?php

namespace App\Modules\Audit\Http\Resources;

use App\Modules\Audit\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditLog
 */
class SecurityEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'occurred_at' => $this->created_at->toJSON(),
            'ip' => $this->ip,
            'user_agent' => $this->user_agent,
            'metadata' => (object) ($this->metadata ?? []),
        ];
    }
}
