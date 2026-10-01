<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Support\ReactionSummary;
use Illuminate\Http\Request;

class MyPostController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->user()->posts()
            ->with(['author.profile', 'media'])
            ->latest('created_at')
            ->latest('id');

        return PostResource::collection(ReactionSummary::apply($query)->paginate(20));
    }
}
