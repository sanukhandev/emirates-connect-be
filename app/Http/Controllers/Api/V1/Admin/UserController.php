<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminSuspendRequest;
use App\Http\Requests\Admin\AdminUserIndexRequest;
use App\Http\Resources\AdminUserResource;
use App\Models\ModerationAuditLog;
use App\Models\User;
use Illuminate\Database\DatabaseManager;

class UserController extends Controller
{
    public function index(AdminUserIndexRequest $request)
    {
        $query = User::query()->with('profile')->withCount(['posts', 'reels'])->latest('created_at')->latest('id');
        if ($request->filled('status')) {
            $query->where('account_status', $request->string('status'));
        }
        if ($request->has('verified')) {
            $query->whereHas('profile', function ($profile) use ($request): void {
                $request->boolean('verified')
                    ? $profile->where('verification_status', 'approved')
                    : $profile->where(fn ($status) => $status->whereNull('verification_status')->orWhere('verification_status', '!=', 'approved'));
            });
        }
        if ($request->filled('q')) {
            $term = $request->string('q')->toString();
            $query->where(fn ($users) => $users->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")->orWhereHas('profile', fn ($profile) => $profile->where('display_name', 'like', "%{$term}%")));
        }

        return AdminUserResource::collection($query->paginate($request->integer('per_page', 20)));
    }

    public function show(User $user): AdminUserResource
    {
        return AdminUserResource::make(User::query()->with('profile')->withCount(['posts', 'reels'])->findOrFail($user->id));
    }

    public function suspend(AdminSuspendRequest $request, User $user, DatabaseManager $database): AdminUserResource
    {
        abort_if($user->is_system_admin, 422, 'System admins cannot be suspended through this endpoint.');

        $database->transaction(function () use ($request, $user): void {
            $user->update(['account_status' => UserStatus::SUSPENDED]);
            ModerationAuditLog::create(['actor_user_id' => $request->user()->id, 'action' => 'user_suspended', 'target_type' => 'user', 'target_id' => $user->id, 'metadata' => ['reason' => $request->validated('reason')], 'created_at' => now()]);
        });

        return AdminUserResource::make($user->fresh('profile'));
    }
}
