<?php

namespace App\Modules\Tasks\Support;

use App\Modules\Tags\Contracts\TagDirectory;
use App\Modules\Tasks\Models\Task;
use Illuminate\Support\Facades\DB;

/** Loads tag summaries for many tasks with two queries (no N+1). */
final class TaskTags
{
    /**
     * @param  iterable<Task>  $tasks
     */
    public static function load(iterable $tasks): void
    {
        $byId = [];
        foreach ($tasks as $task) {
            $byId[$task->id] = $task;
            $task->tagSummaries = [];
        }

        if ($byId === []) {
            return;
        }

        $pairs = DB::table('task_tag')->whereIn('task_id', array_keys($byId))->get(['task_id', 'tag_id']);
        /** @var list<string> $tagIds */
        $tagIds = $pairs->pluck('tag_id')->unique()->values()->all();
        $summaries = app(TagDirectory::class)->summaries($tagIds);

        foreach ($summaries as $tagId => $summary) {
            foreach ($pairs->where('tag_id', $tagId) as $pair) {
                $byId[$pair->task_id]->tagSummaries[] = $summary;
            }
        }
    }
}
