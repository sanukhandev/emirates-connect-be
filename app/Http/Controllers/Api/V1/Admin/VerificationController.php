<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminVerificationIndexRequest;
use App\Http\Requests\Verification\RejectVerificationRequest;
use App\Http\Resources\VerificationAuditResource;
use App\Http\Resources\VerificationRequestResource;
use App\Models\Business;
use App\Models\User;
use App\Models\VerificationAuditLog;
use App\Models\VerificationDocument;
use App\Models\VerificationRequest;
use App\Services\VerificationService;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VerificationController extends Controller
{
    public function index(AdminVerificationIndexRequest $request)
    {
        $query = $this->query()->latest('submitted_at')->latest('id');
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->string('subject_type'));
        }

        return VerificationRequestResource::collection($query->paginate(min($request->integer('per_page', 20), 50)));
    }

    public function show(VerificationRequest $verification): JsonResponse
    {
        return VerificationRequestResource::make($this->query()->with(['documents', 'auditLogs'])->findOrFail($verification->id))->response();
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

    public function download(VerificationRequest $verification, VerificationDocument $document): Response|StreamedResponse
    {
        abort_unless($document->verification_request_id === $verification->id, Response::HTTP_NOT_FOUND);
        VerificationDocument::query()->whereKey($document->id)->firstOrFail()->request->auditLogs()->create(['actor_user_id' => request()->user()->id, 'action' => 'document_accessed', 'metadata' => ['document_id' => $document->id], 'created_at' => now()]);

        $filename = preg_replace('/[^\pL\pN._-]+/u', '-', basename((string) $document->original_filename));
        $filename = trim((string) $filename, '.-') ?: 'verification-document';
        $response = Storage::disk($document->storage_disk)->download($document->storage_path, Str::limit($filename, 180, ''));
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    public function audit(Request $request)
    {
        $query = VerificationAuditLog::query()
            ->with('actor.profile')
            ->latest('created_at')->latest('id');

        if ($request->filled('action')) {
            $query->where('action', $request->string('action'));
        }

        return VerificationAuditResource::collection($query->paginate(min($request->integer('per_page', 20), 50)));
    }

    private function query()
    {
        return VerificationRequest::query()->with([
            'subject' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                User::class => ['profile'],
                Business::class => [],
            ]),
        ]);
    }
}
