<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BusinessStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicUserResource;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\Response;

class BusinessFollowerController extends Controller
{
    public function index(Business $business)
    {
        abort_unless($business->status === BusinessStatus::ACTIVE, Response::HTTP_NOT_FOUND);

        $followers = User::query()
            ->where('account_status', UserStatus::ACTIVE)
            ->whereHas('follows', fn ($query) => $query->where('followable_type', $business->getMorphClass())->where('followable_id', $business->id))
            ->with('profile')
            ->latest('users.created_at')->latest('users.id')
            ->paginate(20);

        return PublicUserResource::collection($followers);
    }
}
