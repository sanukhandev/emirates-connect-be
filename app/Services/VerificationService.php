<?php

namespace App\Services;

use App\Enums\BusinessStatus;
use App\Enums\VerificationStatus;
use App\Events\VerificationReviewed;
use App\Models\Business;
use App\Models\User;
use App\Models\VerificationAuditLog;
use App\Models\VerificationRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VerificationService
{
    public const DISK = 'verification_private';

    public const ACCESS_TTL_MINUTES = 5;

    public function submit(User $actor, Model $subject, array $data, array $files, array $types): VerificationRequest
    {
        if ($subject instanceof Business && $subject->status !== BusinessStatus::ACTIVE) {
            abort(404);
        }

        $stored = [];
        try {
            return DB::transaction(function () use ($actor, $subject, $data, $files, $types, &$stored): VerificationRequest {
                $locked = $subject::query()->whereKey($subject->getKey())->lockForUpdate()->firstOrFail();
                $existing = VerificationRequest::query()->whereMorphedTo('subject', $locked)->whereIn('status', [VerificationStatus::PENDING, VerificationStatus::APPROVED])->exists();
                if ($existing) {
                    throw new HttpResponseException(response()->json(['message' => 'Verification request cannot be submitted in the current state.'], 409));
                }

                $request = VerificationRequest::create([
                    'subject_type' => $locked->getMorphClass(), 'subject_id' => $locked->getKey(),
                    'status' => VerificationStatus::PENDING, 'submitted_by_user_id' => $actor->id,
                    'submitted_at' => now(), 'data' => $data,
                ]);

                foreach (array_values($files) as $index => $file) {
                    if (! $file instanceof UploadedFile) {
                        continue;
                    }
                    $path = $file->store('verification/'.$request->id.'/'.Str::uuid(), self::DISK);
                    $stored[] = $path;
                    $request->documents()->create([
                        'document_type' => $types[$index] ?? 'other_supporting_document',
                        'storage_disk' => self::DISK, 'storage_path' => $path,
                        'original_filename' => Str::limit($file->getClientOriginalName(), 255, ''),
                        'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size' => $file->getSize(),
                        'uploaded_by_user_id' => $actor->id,
                    ]);
                }

                $this->setSubjectStatus($locked, VerificationStatus::PENDING);
                $this->audit($request, $actor, 'submitted', null, VerificationStatus::PENDING);

                return $request->load(['subject', 'documents', 'auditLogs']);
            });
        } catch (\Throwable $exception) {
            foreach ($stored as $path) {
                Storage::disk(self::DISK)->delete($path);
            }
            throw $exception;
        }
    }

    public function review(VerificationRequest $request, User $admin, VerificationStatus $status, ?string $reason = null): VerificationRequest
    {
        $updated = DB::transaction(function () use ($request, $admin, $status, $reason): VerificationRequest {
            $request = VerificationRequest::query()->with('subject')->lockForUpdate()->findOrFail($request->id);
            if ($request->status !== VerificationStatus::PENDING) {
                throw new HttpResponseException(response()->json(['message' => 'Only pending requests can be reviewed.'], 409));
            }
            $from = $request->status;
            $request->update(['status' => $status, 'reviewed_by_user_id' => $admin->id, 'reviewed_at' => now(), 'rejection_reason' => $reason]);
            $this->setSubjectStatus($request->subject, $status);
            $this->audit($request, $admin, $status === VerificationStatus::APPROVED ? 'approved' : 'rejected', $from, $status);

            return $request->fresh(['subject', 'documents', 'auditLogs']);
        });
        event(new VerificationReviewed($updated));

        return $updated;
    }

    private function setSubjectStatus(Model $subject, VerificationStatus $status): void
    {
        if ($subject instanceof User) {
            $subject->profile()->updateOrCreate(['user_id' => $subject->id], ['verification_status' => $status]);
        }
        if ($subject instanceof Business) {
            $subject->update(['verification_status' => $status]);
        }
    }

    private function audit(VerificationRequest $request, User $actor, string $action, ?VerificationStatus $from, VerificationStatus $to): void
    {
        VerificationAuditLog::create(['verification_request_id' => $request->id, 'actor_user_id' => $actor->id, 'action' => $action, 'from_status' => $from?->value, 'to_status' => $to->value, 'created_at' => now()]);
    }
}
