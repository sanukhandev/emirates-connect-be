<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use Illuminate\Http\Request;

class MyPostController extends Controller
{
    public function index(Request $request)
    {
        return PostResource::collection($request->user()->posts()
            ->with(['author.profile', 'media'])
            ->latest('created_at')
            ->latest('id')
            ->paginate(20));
    }
}
