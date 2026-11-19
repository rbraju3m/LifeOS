<?php

namespace App\Modules\Tasks\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Shared\Http\Concurrency\Version;
use App\Modules\Shared\Http\Problems\ApiProblemException;
use App\Modules\Spaces\Models\Space;
use App\Modules\Tasks\Models\TaskList;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SaveTaskList
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Space $space, User $creator, array $data): TaskList
    {
        $list = new TaskList(Arr::only($this->normalise($data), ['name', 'color', 'icon', 'sort_order', 'visibility']));
        $list->forceFill(['space_id' => $space->id, 'created_by' => $creator->id])->save();

        return $list->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Request $request, TaskList $list, User $editor, array $data): TaskList
    {
        return DB::transaction(function () use ($request, $list, $editor, $data): TaskList {
            $locked = TaskList::query()->whereKey($list->id)->lockForUpdate()->firstOrFail();
            Version::assertMatches($request, $locked);

            if (isset($data['visibility']) && $data['visibility'] !== $locked->visibility->value
                && $locked->created_by !== $editor->id) {
                throw new ApiProblemException(403, 'forbidden', __('tasks.visibility_creator_only'));
            }

            $locked->fill(Arr::only($this->normalise($data), $locked->getFillable()))->save();

            return $locked->refresh();
        });
    }

    public function archive(TaskList $list, bool $archived): TaskList
    {
        $list->forceFill(['archived_at' => $archived ? ($list->archived_at ?? now()) : null])->save();

        return $list->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalise(array $data): array
    {
        if (isset($data['color']) && is_string($data['color'])) {
            $data['color'] = mb_strtolower($data['color']);
        }

        return $data;
    }
}
