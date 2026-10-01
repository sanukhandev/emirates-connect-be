<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BusinessRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Business\AddBusinessMemberRequest;
use App\Http\Requests\Business\UpdateBusinessMemberRequest;
use App\Http\Resources\BusinessMemberResource;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class BusinessMemberController extends Controller
{
    public function index(Request $request, Business $business)
    {
        $this->authorize('manageMembers', $business);

        return BusinessMemberResource::collection($business->members()->with(['user.profile'])->paginate(20));
    }

    public function store(AddBusinessMemberRequest $request, Business $business): JsonResponse
    {
        $role = BusinessRole::from($request->validated('role'));
        $this->authorize('addMember', [$business, $role]);
        $user = User::findOrFail($request->integer('user_id'));
        if ($user->account_status !== UserStatus::ACTIVE) {
            throw ValidationException::withMessages(['user_id' => 'The selected user is not active.']);
        }
        if ($business->members()->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages(['user_id' => 'The selected user is already a member.']);
        }

        $member = $business->members()->create(['user_id' => $user->id, 'role' => $role]);

        return BusinessMemberResource::make($member->load('user.profile'))->response()->setStatusCode(201);
    }

    public function update(UpdateBusinessMemberRequest $request, Business $business, int $member): JsonResponse
    {
        $membership = $this->member($business, $member);
        $role = BusinessRole::from($request->validated('role'));
        $this->authorize('updateMember', [$business, $membership, $role]);
        $membership->update(['role' => $role]);

        return BusinessMemberResource::make($membership->refresh()->load('user.profile'))->response()->setStatusCode(200);
    }

    public function destroy(Request $request, Business $business, int $member): Response
    {
        $membership = $this->member($business, $member);
        $this->authorize('removeMember', [$business, $membership]);
        abort_if($membership->user_id === $request->user()->id, 403);
        $membership->delete();

        return response()->noContent();
    }

    private function member(Business $business, int $id): BusinessMember
    {
        return $business->members()->findOrFail($id);
    }
}
