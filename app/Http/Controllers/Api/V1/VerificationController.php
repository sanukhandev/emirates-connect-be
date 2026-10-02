<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Verification\SubmitBusinessVerificationRequest;
use App\Http\Requests\Verification\SubmitUserVerificationRequest;
use App\Http\Resources\VerificationRequestResource;
use App\Models\Business;
use App\Models\VerificationRequest;
use App\Services\VerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function userStatus(Request $request): JsonResponse
    {
        $user = $request->user()->load('profile');
        $latest = VerificationRequest::query()->whereMorphedTo('subject', $user)->latest('id')->with('documents')->first();

        return response()->json(['data' => ['status' => $user->profile?->verification_status?->value ?? VerificationStatus::NOT_SUBMITTED->value, 'request' => $latest ? VerificationRequestResource::make($latest) : null]]);
    }

    public function submitUser(SubmitUserVerificationRequest $request, VerificationService $service): JsonResponse
    {
        $user = $request->user();
        $verification = $service->submit($user, $user, ['legal_name' => $request->validated('legal_name')], $request->file('documents', []), $request->validated('document_types'));

        return VerificationRequestResource::make($verification)->response()->setStatusCode(201);
    }

    public function businessStatus(Request $request, Business $business): JsonResponse
    {
        $this->authorize('verify', $business);
        $latest = VerificationRequest::query()->whereMorphedTo('subject', $business)->latest('id')->with(['documents', 'auditLogs'])->first();

        return response()->json(['data' => ['status' => $business->verification_status?->value ?? VerificationStatus::NOT_SUBMITTED->value, 'request' => $latest ? VerificationRequestResource::make($latest) : null]]);
    }

    public function submitBusiness(SubmitBusinessVerificationRequest $request, Business $business, VerificationService $service): JsonResponse
    {
        $this->authorize('verify', $business);
        $data = $request->validated();
        $types = $data['document_types'];
        unset($data['document_types'], $data['documents']);
        $verification = $service->submit($request->user(), $business, $data, $request->file('documents', []), $types);

        return VerificationRequestResource::make($verification)->response()->setStatusCode(201);
    }
}
