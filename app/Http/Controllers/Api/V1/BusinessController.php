<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BusinessRole;
use App\Enums\BusinessStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Business\CreateBusinessRequest;
use App\Http\Requests\Business\UpdateBusinessRequest;
use App\Http\Resources\BusinessResource;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BusinessController extends Controller
{
    public function store(CreateBusinessRequest $request): JsonResponse
    {
        $business = DB::transaction(function () use ($request): Business {
            $data = $request->validated();
            $business = Business::create($data + [
                'slug' => $this->uniqueSlug($data['name']),
                'created_by' => $request->user()->id,
                'status' => BusinessStatus::ACTIVE,
            ]);
            $business->members()->create(['user_id' => $request->user()->id, 'role' => BusinessRole::OWNER]);

            return $business;
        });

        return BusinessResource::make($business->refresh())->response()->setStatusCode(201);
    }

    public function show(Request $request, Business $business): JsonResponse
    {
        abort_unless($business->status === BusinessStatus::ACTIVE, 404);

        return BusinessResource::make($business)->response()->setStatusCode(200);
    }

    public function update(UpdateBusinessRequest $request, Business $business): JsonResponse
    {
        $this->authorize('update', $business);
        $business->update($request->validated());

        return BusinessResource::make($business->refresh())->response()->setStatusCode(200);
    }

    public function destroy(Request $request, Business $business): Response
    {
        $this->authorize('delete', $business);
        $business->update(['status' => BusinessStatus::INACTIVE]);

        return response()->noContent();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'business';
        $slug = $base;
        $number = 2;
        while (Business::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$number}";
            $number++;
        }

        return $slug;
    }
}
