<?php

namespace App\Modules\Tags\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Shared\Http\Concurrency\Version;
use App\Modules\Spaces\Models\Space;
use App\Modules\Tags\Models\Tag;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates and updates tags. Authorization happens in the controller; this
 * sets the ownership columns and turns a lost race on the unique
 * (space_id, name) index into the same 422 the validator gives.
 */
class SaveTag
{
    /**
     * @param  array{name: string, color?: string|null}  $data
     */
    public function create(Space $space, User $creator, array $data): Tag
    {
        $tag = new Tag($this->normalise($data));
        $tag->forceFill(['space_id' => $space->id, 'created_by' => $creator->id]);

        return $this->guardUnique(function () use ($tag): Tag {
            $tag->save();

            return $tag;
        });
    }

    /**
     * @param  array{name?: string, color?: string|null}  $data
     */
    public function update(Request $request, Tag $tag, array $data): Tag
    {
        return $this->guardUnique(fn (): Tag => DB::transaction(function () use ($request, $tag, $data): Tag {
            $locked = Tag::query()->whereKey($tag->id)->lockForUpdate()->firstOrFail();
            Version::assertMatches($request, $locked);

            $locked->fill($this->normalise($data))->save();

            return $locked;
        }));
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

    /**
     * @param  callable(): Tag  $save
     */
    private function guardUnique(callable $save): Tag
    {
        try {
            return $save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => __('validation.unique', ['attribute' => 'name'])]);
        }
    }
}
