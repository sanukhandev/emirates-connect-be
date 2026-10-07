<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\SetReactionRequest;
use App\Http\Resources\ReelResource;
use App\Models\Reel;
use App\Services\ReactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReelReactionController extends Controller
{
    public function update(SetReactionRequest $request, Reel $reel, ReactionService $service): JsonResponse
    {
        return ReelResource::make($service->set($request->user(), $reel, ReactionType::from($request->validated('type'))))
            ->response()->setStatusCode(Response::HTTP_OK);
    }

    public function destroy(Request $request, Reel $reel, ReactionService $service): Response
    {
        $service->remove($request->user(), $reel);

        return response()->noContent();
    }
}
