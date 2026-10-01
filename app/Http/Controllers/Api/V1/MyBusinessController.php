<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessResource;
use Illuminate\Http\Request;

class MyBusinessController extends Controller
{
    public function index(Request $request)
    {
        $businesses = $request->user()->businesses()
            ->with(['members' => fn ($query) => $query->where('user_id', $request->user()->id)])
            ->orderBy('businesses.name')
            ->paginate(20);

        return BusinessResource::collection($businesses);
    }
}
