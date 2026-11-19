<?php

namespace App\Modules\Tags\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Shared\Http\Pagination\PerPage;
use App\Modules\Spaces\Models\Space;
use App\Modules\Tags\Actions\SaveTag;
use App\Modules\Tags\Http\Requests\TagRequest;
use App\Modules\Tags\Http\Resources\TagResource;
use App\Modules\Tags\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/** /spaces/{space}/tags (docs/blueprint/12 §12.5). {tag} is bound inside {space}. */
class TagController
{
    public function index(Request $request, Space $space): AnonymousResourceCollection
    {
        Gate::authorize('view', $space);

        /** @var User $user */
        $user = $request->user();

        $tags = Tag::query()
            ->visibleTo($user)
            ->where('space_id', $space->id)
            ->orderBy('name')
            ->orderBy('id')
            ->cursorPaginate(PerPage::fromRequest($request));

        return TagResource::collection($tags);
    }

    public function store(TagRequest $request, Space $space, SaveTag $save): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array{name: string, color?: string|null} $data */
        $data = $request->validated();

        return (new TagResource($save->create($space, $user, $data)))->response()->setStatusCode(201);
    }

    public function show(Space $space, Tag $tag): TagResource
    {
        Gate::authorize('view', $tag);

        return new TagResource($tag);
    }

    public function update(TagRequest $request, Space $space, Tag $tag, SaveTag $save): TagResource
    {
        /** @var array{name?: string, color?: string|null} $data */
        $data = $request->validated();

        return new TagResource($save->update($request, $tag, $data));
    }

    public function destroy(Space $space, Tag $tag): Response
    {
        Gate::authorize('delete', $tag);

        $tag->delete();

        return response()->noContent();
    }
}
