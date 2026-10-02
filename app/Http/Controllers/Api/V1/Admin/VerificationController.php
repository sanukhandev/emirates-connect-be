<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Verification\RejectVerificationRequest;
use App\Http\Resources\VerificationRequestResource;
use App\Models\VerificationDocument;
use App\Models\VerificationRequest;
use App\Services\VerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class VerificationController extends Controller
{
    public function index(Request $request)
    {
        $query = VerificationRequest::query()->with(['subject', 'documents'])->latest('submitted_at')->latest('id');
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->string('subject_type'));
        }

        return VerificationRequestResource::collection($query->paginate(20));
    }

    public function show(VerificationRequest $verification): JsonResponse
    {
        return VerificationRequestResource::make($verification->load(['subject', 'documents', 'auditLogs']))->response();
    }

    public function approve(VerificationRequest $verification, Request $request, VerificationService $service): JsonResponse
    {
        $updated = $service->review($verification, $request->user(), VerificationStatus::APPROVED);

        return VerificationRequestResource::make($updated)->response();
    }

    public function reject(RejectVerificationRequest $request, VerificationRequest $verification, VerificationService $service): JsonResponse
    {
        $updated = $service->review($verification, $request->user(), VerificationStatus::REJECTED, $request->validated('rejection_reason'));

        return VerificationRequestResource::make($updated)->response();
    }

    public function document(VerificationRequest $verification, VerificationDocument $document, Request $request): JsonResponse
    {
        abort_unless($document->verification_request_id === $verification->id, Response::HTTP_NOT_FOUND);
        $url = URL::temporarySignedRoute('verification.document.download', now()->addMinutes(VerificationService::ACCESS_TTL_MINUTES), ['verification' => $verification->id, 'document' => $document->id]);

        return response()->json(['data' => ['url' => $url, 'expires_at' => now()->addMinutes(VerificationService::ACCESS_TTL_MINUTES)->toISOString()]]);
    }

    public function download(VerificationRequest $verification, VerificationDocument $document): Response
    {
        abort_unless($document->verification_request_id === $verification->id, Response::HTTP_NOT_FOUND);
        VerificationDocument::query()->whereKey($document->id)->firstOrFail()->request->auditLogs()->create(['actor_user_id' => request()->user()->id, 'action' => 'document_accessed', 'metadata' => ['document_id' => $document->id], 'created_at' => now()]);

        return Storage::disk($document->storage_disk)->download($document->storage_path, $document->original_filename ?: 'verification-document');
    }
}
