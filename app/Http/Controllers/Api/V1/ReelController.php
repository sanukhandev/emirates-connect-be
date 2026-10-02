<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\FeedRequest;
use App\Http\Requests\Reel\StoreReelRequest;
use App\Http\Requests\Reel\UpdateReelRequest;
use App\Http\Requests\Reel\UploadReelVideoRequest;
use App\Http\Resources\ReelResource;
use App\Models\Business;
use App\Models\Reel;
use App\Models\User;
use App\Services\ReelProcessingService;
use App\Services\ReelService;
use App\Services\ReelVisibility;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReelController extends Controller
{
    public function index(FeedRequest $request, ReelVisibility $visibility)
    {
        return ReelResource::collection($this->feedQuery($visibility)->cursorPaginate(
            $request->integer('per_page', 20), ['*'], 'cursor', $request->input('cursor'),
        ));
    }

    public function store(StoreReelRequest $request, ReelService $service): JsonResponse
    {
        return ReelResource::make($service->create($request->user(), $request->validated()))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function upload(UploadReelVideoRequest $request, Reel $reel, ReelService $service, ReelProcessingService $processor): JsonResponse
    {
        return ReelResource::make($service->upload($request->user(), $reel, $request->file('video'), $processor))
            ->response()->setStatusCode(Response::HTTP_OK);
    }

    public function show(Request $request, Reel $reel, ReelVisibility $visibility): JsonResponse
    {
        $reel->loadMorph('author', [User::class => ['profile'], Business::class => []]);
        abort_unless($visibility->isPublic($reel) || ($request->user() && $request->user()->can('view', $reel)), Response::HTTP_NOT_FOUND);

        return ReelResource::make($reel)->response()->setStatusCode(Response::HTTP_OK);
    }

    public function update(UpdateReelRequest $request, Reel $reel, ReelService $service): JsonResponse
    {
        $this->authorize('update', $reel);

        return ReelResource::make($service->update($reel, $request->validated()))
            ->response()->setStatusCode(Response::HTTP_OK);
    }

    public function destroy(Request $request, Reel $reel, ReelService $service): Response
    {
        $this->authorize('delete', $reel);
        $service->delete($reel);

        return response()->noContent();
    }

    public function me(FeedRequest $request): mixed
    {
        $query = Reel::query()
            ->where('author_type', 'user')
            ->where('author_id', $request->user()->id)
            ->with(['author.profile'])
            ->latest('created_at')
            ->latest('id');

        return ReelResource::collection($query->cursorPaginate($request->integer('per_page', 20), ['*'], 'cursor', $request->input('cursor')));
    }

    public function user(FeedRequest $request, User $user, ReelVisibility $visibility): mixed
    {
        abort_if($user->account_status?->blocksAccess(), Response::HTTP_NOT_FOUND);

        return ReelResource::collection($this->feedQuery($visibility)->where('author_type', 'user')->where('author_id', $user->id)->cursorPaginate(
            $request->integer('per_page', 20), ['*'], 'cursor', $request->input('cursor'),
        ));
    }

    public function business(FeedRequest $request, Business $business, ReelVisibility $visibility): mixed
    {
        abort_unless($business->status?->value === 'active', Response::HTTP_NOT_FOUND);

        return ReelResource::collection($this->feedQuery($visibility)->where('author_type', 'business')->where('author_id', $business->id)->cursorPaginate(
            $request->integer('per_page', 20), ['*'], 'cursor', $request->input('cursor'),
        ));
    }

    private function feedQuery(ReelVisibility $visibility)
    {
        return $visibility->publicQuery()
            ->with(['author' => function (MorphTo $morphTo): void {
                $morphTo->morphWith([User::class => ['profile'], Business::class => []]);
            }])
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }
}
