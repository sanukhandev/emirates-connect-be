<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Enums\BusinessStatus;
use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\User;
use App\Models\VerificationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class VerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_submission_is_private_and_cannot_be_impersonated_or_duplicated(): void
    {
        Storage::fake('verification_private');
        $user = User::factory()->create();
        $other = User::factory()->create();

        $response = $this->actingAs($user)->post('/api/v1/verification/user', [
            'user_id' => $other->id, 'legal_name' => 'User Legal Name',
            'document_types' => ['identity_document'],
            'documents' => [UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf')],
        ]);

        $response->assertCreated()->assertJsonPath('status', 'pending')->assertJsonMissingPath('storage_path');
        $request = VerificationRequest::firstOrFail();
        $this->assertSame($user->id, $request->subject_id);
        $this->assertTrue(Storage::disk('verification_private')->exists($request->documents()->firstOrFail()->storage_path));
        $this->actingAs($user)->post('/api/v1/verification/user', [
            'legal_name' => 'Again', 'document_types' => ['identity_document'],
            'documents' => [UploadedFile::fake()->create('again.pdf', 10, 'application/pdf')],
        ])->assertStatus(409);
        $this->getJson('/api/v1/verification/user')->assertOk()->assertJsonPath('data.status', 'pending');
    }

    public function test_business_owner_and_admin_can_submit_but_editor_cannot(): void
    {
        Storage::fake('verification_private');
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $editor = User::factory()->create();
        $business = Business::factory()->create(['created_by' => $owner->id, 'status' => BusinessStatus::ACTIVE]);
        BusinessMember::create(['business_id' => $business->id, 'user_id' => $owner->id, 'role' => BusinessRole::OWNER]);
        BusinessMember::create(['business_id' => $business->id, 'user_id' => $admin->id, 'role' => BusinessRole::ADMIN]);
        BusinessMember::create(['business_id' => $business->id, 'user_id' => $editor->id, 'role' => BusinessRole::EDITOR]);
        $payload = fn () => ['legal_business_name' => 'Legal Business', 'registration_number' => 'REG-1', 'document_types' => ['trade_licence'], 'documents' => [UploadedFile::fake()->create('licence.pdf', 100, 'application/pdf')]];

        $this->actingAs($editor)->post('/api/v1/verification/business/'.$business->slug, $payload())->assertForbidden();
        $this->actingAs($owner)->post('/api/v1/verification/business/'.$business->slug, $payload())->assertCreated();
        $this->actingAs($admin)->getJson('/api/v1/verification/business/'.$business->slug)->assertOk()->assertJsonPath('data.status', 'pending');
    }

    public function test_admin_approval_rejection_resubmission_and_public_badge(): void
    {
        Storage::fake('verification_private');
        $user = User::factory()->create();
        $admin = User::factory()->create(['is_system_admin' => true]);
        $payload = ['legal_name' => 'Legal Name', 'document_types' => ['identity_document'], 'documents' => [UploadedFile::fake()->create('id.pdf', 100, 'application/pdf')]];

        $this->actingAs($user)->post('/api/v1/verification/user', $payload)->assertCreated();
        $verification = VerificationRequest::firstOrFail();
        $this->actingAs($admin)->getJson('/api/v1/admin/verifications')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($admin)->postJson('/api/v1/admin/verifications/'.$verification->id.'/approve')->assertOk();
        $this->assertDatabaseHas('verification_requests', ['id' => $verification->id, 'status' => VerificationStatus::APPROVED->value]);
        $this->getJson('/api/v1/users/'.$user->id)->assertOk()->assertJsonPath('data.is_verified', true);
        $this->actingAs($user)->post('/api/v1/verification/user', $payload)->assertStatus(409);

        $rejectedUser = User::factory()->create();
        $this->actingAs($rejectedUser)->post('/api/v1/verification/user', $payload)->assertCreated();
        $rejected = VerificationRequest::query()->where('subject_id', $rejectedUser->id)->firstOrFail();
        $this->actingAs($admin)->postJson('/api/v1/admin/verifications/'.$rejected->id.'/reject', ['rejection_reason' => 'Please provide a clearer document.'])->assertOk();
        $this->actingAs($rejectedUser)->post('/api/v1/verification/user', $payload)->assertCreated();
        $this->assertSame(2, VerificationRequest::query()->where('subject_id', $rejectedUser->id)->count());
    }

    public function test_admin_document_access_is_temporary_authorized_and_idor_safe(): void
    {
        Storage::fake('verification_private');
        $user = User::factory()->create();
        $admin = User::factory()->create(['is_system_admin' => true]);
        $this->actingAs($user)->post('/api/v1/verification/user', ['legal_name' => 'Legal', 'document_types' => ['identity_document'], 'documents' => [UploadedFile::fake()->create('id.pdf', 10, 'application/pdf')]])->assertCreated();
        $verification = VerificationRequest::with('documents')->firstOrFail();
        $document = $verification->documents->firstOrFail();
        $url = $this->actingAs($admin)->getJson('/api/v1/admin/verifications/'.$verification->id.'/documents/'.$document->id)->assertOk()->json('data.url');
        $this->assertStringContainsString('signature=', $url);
        Auth::forgetGuards();
        $this->getJson($url)->assertUnauthorized();
        $download = $this->actingAs($admin)->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('private', (string) $download->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $download->headers->get('Cache-Control'));
        $document->update(['original_filename' => "review\r\nunsafe.pdf"]);
        $this->actingAs($admin)->get($url)->assertOk()->assertHeader('Content-Disposition', 'attachment; filename=review-unsafe.pdf');
        $this->actingAs(User::factory()->create())->getJson($url)->assertForbidden();
        $businessAdmin = User::factory()->create();
        $business = Business::factory()->create(['created_by' => $businessAdmin->id, 'status' => BusinessStatus::ACTIVE]);
        BusinessMember::create(['business_id' => $business->id, 'user_id' => $businessAdmin->id, 'role' => BusinessRole::OWNER]);
        $this->actingAs($businessAdmin)->getJson($url)->assertForbidden();
        $suspendedAdmin = User::factory()->create(['is_system_admin' => true, 'account_status' => UserStatus::SUSPENDED]);
        $suspendedUrl = $this->actingAs($admin)->getJson('/api/v1/admin/verifications/'.$verification->id.'/documents/'.$document->id)->json('data.url');
        $this->actingAs($suspendedAdmin)->getJson($suspendedUrl)->assertForbidden();
        $this->actingAs($admin)->getJson(str_replace('signature=', 'signature=tampered', $url))->assertForbidden();
        $expiredUrl = URL::temporarySignedRoute('verification.document.download', now()->subMinute(), ['verification' => $verification->id, 'document' => $document->id]);
        $this->actingAs($admin)->getJson($expiredUrl)->assertForbidden();
        $this->actingAs(User::factory()->create())->getJson('/api/v1/admin/verifications/'.$verification->id.'/documents/'.$document->id)->assertForbidden();
        $other = VerificationRequest::create(['subject_type' => 'user', 'subject_id' => $user->id, 'status' => VerificationStatus::REJECTED, 'submitted_by_user_id' => $user->id, 'submitted_at' => now()]);
        $this->actingAs($admin)->getJson('/api/v1/admin/verifications/'.$other->id.'/documents/'.$document->id)->assertNotFound();
        $otherUrl = URL::temporarySignedRoute('verification.document.download', now()->addMinutes(5), ['verification' => $other->id, 'document' => $document->id]);
        $this->actingAs($admin)->getJson($otherUrl)->assertNotFound();
    }
}
