<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use App\Services\ReportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function store(StoreReportRequest $request, ReportService $service)
    {
        return ReportResource::make($service->create($request->user(), $request->validated()))->response()->setStatusCode(201);
    }

    public function mine(Request $request): mixed
    {
        return ReportResource::collection(Report::query()->where('reporter_user_id', $request->user()->id)->latest('created_at')->latest('id')->cursorPaginate(min($request->integer('per_page', 20), 50)));
    }
}
