<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\BusinessStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminBusinessIndexRequest;
use App\Http\Requests\Admin\AdminSuspendRequest;
use App\Http\Resources\AdminBusinessResource;
use App\Models\Business;
use App\Models\ModerationAuditLog;
use Illuminate\Database\DatabaseManager;

class BusinessController extends Controller
{
    public function index(AdminBusinessIndexRequest $request)
    {
        return AdminBusinessResource::collection($this->query($request)->paginate($request->integer('per_page', 20)));
    }

    public function show(Business $business): AdminBusinessResource
    {
        return AdminBusinessResource::make($this->query()->findOrFail($business->id));
    }

    public function suspend(AdminSuspendRequest $request, Business $business, DatabaseManager $database): AdminBusinessResource
    {
        $database->transaction(function () use ($request, $business): void {
            $business->update(['status' => BusinessStatus::SUSPENDED]);
            ModerationAuditLog::create(['actor_user_id' => $request->user()->id, 'action' => 'business_suspended', 'target_type' => 'business', 'target_id' => $business->id, 'metadata' => ['reason' => $request->validated('reason')], 'created_at' => now()]);
        });

        return AdminBusinessResource::make($this->query()->findOrFail($business->id));
    }

    private function query(?AdminBusinessIndexRequest $request = null)
    {
        $query = Business::query()->with(['owner.user.profile'])->withCount(['members', 'posts', 'reels'])->latest('created_at')->latest('id');
        if ($request?->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request?->has('verified')) {
            $request->boolean('verified')
                ? $query->where('verification_status', 'approved')
                : $query->where(fn ($status) => $status->whereNull('verification_status')->orWhere('verification_status', '!=', 'approved'));
        }
        if ($request?->filled('q')) {
            $term = $request->string('q')->toString();
            $query->where(fn ($businesses) => $businesses->where('name', 'like', "%{$term}%")->orWhere('slug', 'like', "%{$term}%"));
        }

        return $query;
    }
}
