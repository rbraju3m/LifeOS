<?php

namespace App\Modules\Tasks\Http\Resources;

use App\Modules\Shared\Http\Concurrency\Version;
use App\Modules\Tags\Contracts\TagSummary;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Support\DueFields;
use App\Modules\Tasks\Support\TaskTags;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->tagSummaries === null) {
            TaskTags::load([$this->resource]);
        }

        return [
            'id' => $this->id,
            'space_id' => $this->space_id,
            'task_list_id' => $this->task_list_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'priority' => $this->priority,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'due_time' => DueFields::localTime($this->resource),
            'due_at' => $this->due_at?->toJSON(),
            'timezone' => $this->timezone,
            'rrule' => $this->rrule,
            'recurrence_series_id' => $this->recurrence_series_id,
            'visibility' => $this->visibility->value,
            'assignee_user_id' => $this->assignee_user_id,
            'created_by' => $this->created_by,
            'completed_at' => $this->completed_at?->toJSON(),
            'completed_by' => $this->completed_by,
            'sort_order' => $this->sort_order,
            'tags' => array_map(fn (TagSummary $tag) => $tag->toArray(), $this->tagSummaries ?? []),
            'created_at' => $this->created_at->toJSON(),
            'updated_at' => $this->updated_at->toJSON(),
            'deleted_at' => $this->deleted_at?->toJSON(),
            'version' => Version::of($this->resource),
        ];
    }
}
