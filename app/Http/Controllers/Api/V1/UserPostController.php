<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\User;
use App\Support\ReactionSummary;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserPostController extends Controller
{
    public function index(Request $request, User $user)
    {
        abort_if($user->account_status?->blocksAccess(), Response::HTTP_NOT_FOUND);

        $query = $user->posts()
            ->where('status', PostStatus::PUBLISHED)
            ->with(['author.profile', 'media'])
            ->latest('published_at')
            ->latest('id');

        return PostResource::collection(ReactionSummary::apply($query)->paginate(20));
    }
}
