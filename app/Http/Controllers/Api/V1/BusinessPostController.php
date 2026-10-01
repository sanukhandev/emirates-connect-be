<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BusinessStatus;
use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Business;
use Illuminate\Http\Request;

class BusinessPostController extends Controller
{
    public function index(Request $request, Business $business)
    {
        abort_unless($business->status === BusinessStatus::ACTIVE, 404);

        return PostResource::collection($business->posts()
            ->where('status', PostStatus::PUBLISHED)
            ->with(['author', 'media'])
            ->latest('published_at')
            ->latest('id')
            ->paginate(20));
    }
}
