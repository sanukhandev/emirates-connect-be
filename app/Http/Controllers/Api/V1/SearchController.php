<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchRequest;
use App\Http\Resources\SearchResultResource;
use App\Services\SearchService;

class SearchController extends Controller
{
    public function index(SearchRequest $request, SearchService $service)
    {
        return SearchResultResource::collection($service->search($request->validated()));
    }

    public function users(SearchRequest $request, SearchService $service)
    {
        return SearchResultResource::collection($service->search(array_merge($request->validated(), ['type' => 'users'])));
    }

    public function businesses(SearchRequest $request, SearchService $service)
    {
        return SearchResultResource::collection($service->search(array_merge($request->validated(), ['type' => 'businesses'])));
    }
}
