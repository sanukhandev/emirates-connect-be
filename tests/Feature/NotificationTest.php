<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Enums\VerificationStatus;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\Comment;
use App\Models\Notification;
use App\Models\Post;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Services\VerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_follow_comment_reply_and_reaction_events_create_safe_notifications_once(): void
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $post = Post::factory()->for($owner, 'author')->create(['created_by' => $owner->id]);

        $this->actingAs($actor)->putJson("/api/v1/users/{$owner->id}/follow")->assertOk();
        $this->actingAs($actor)->putJson("/api/v1/users/{$owner->id}/follow")->assertOk();
        $this->assertDatabaseCount('notifications', 1);

        $comment = $this->actingAs($actor)->postJson("/api/v1/posts/{$post->id}/comments", ['author_type' => 'user', 'body' => 'Hello'])->assertCreated()->json('data');
        $commentModel = Comment::findOrFail($comment['id']);
        $this->actingAs($owner)->postJson("/api/v1/comments/{$commentModel->id}/replies", ['author_type' => 'user', 'body' => 'Reply'])->assertCreated();
        $this->actingAs($actor)->putJson("/api/v1/posts/{$post->id}/reaction", ['type' => 'like'])->assertOk();
        $this->actingAs($actor)->putJson("/api/v1/posts/{$post->id}/reaction", ['type' => 'celebrate'])->assertOk();

        $this->assertSame(3, Notification::query()->where('recipient_user_id', $owner->id)->count());
        $this->assertSame(1, Notification::query()->where('recipient_user_id', $actor->id)->count());
        $this->actingAs($owner)->getJson('/api/v1/notifications')->assertOk()
            ->assertJsonMissingPath('data.0.recipient_user_id')
            ->assertJsonMissingPath('data.0.actor.email');
    }

    public function test_listing_count_mark_read_and_mark_all_are_recipient_scoped(): void
    {
        $recipient = User::factory()->create();
        $other = User::factory()->create();
        $make = fn (User $user, ?string $readAt = null) => Notification::create(['recipient_user_id' => $user->id, 'type' => 'followed', 'data' => [], 'read_at' => $readAt]);
        foreach (range(1, 3) as $_) {
            $make($recipient);
        }
        foreach (range(1, 2) as $_) {
            $make($recipient, now());
        }
        foreach (range(1, 4) as $_) {
            $make($other);
        }

        $this->actingAs($recipient)->getJson('/api/v1/notifications/unread-count')->assertOk()->assertJsonPath('count', 3);
        $this->actingAs($recipient)->getJson('/api/v1/notifications?unread=true')->assertOk()->assertJsonCount(3, 'data');
        $notification = Notification::where('recipient_user_id', $recipient->id)->firstOrFail();
        $this->actingAs($recipient)->patchJson("/api/v1/notifications/{$notification->id}/read")->assertOk();
        $this->actingAs($other)->patchJson("/api/v1/notifications/{$notification->id}/read")->assertNotFound();
        $this->actingAs($recipient)->postJson('/api/v1/notifications/read-all')->assertOk()->assertJsonPath('updated', 2);
        $this->assertDatabaseCount('notifications', 9);
        $this->assertSame(4, Notification::where('recipient_user_id', $other->id)->whereNull('read_at')->count());
    }

    public function test_verification_result_notifies_user_and_business_owner_without_reviewer_data(): void
    {
        Storage::fake('verification_private');
        $user = User::factory()->create();
        $admin = User::factory()->create(['is_system_admin' => true]);
        $this->actingAs($user)->post('/api/v1/verification/user', ['legal_name' => 'Legal', 'document_types' => ['identity_document'], 'documents' => [UploadedFile::fake()->create('id.pdf', 10, 'application/pdf')]])->assertCreated();
        $request = VerificationRequest::where('subject_id', $user->id)->firstOrFail();
        app(VerificationService::class)->review($request, $admin, VerificationStatus::APPROVED);

        $owner = User::factory()->create();
        $business = Business::factory()->create(['created_by' => $owner->id]);
        BusinessMember::create(['business_id' => $business->id, 'user_id' => $owner->id, 'role' => BusinessRole::OWNER]);
        $businessRequest = VerificationRequest::create(['subject_type' => 'business', 'subject_id' => $business->id, 'status' => VerificationStatus::PENDING, 'submitted_by_user_id' => $owner->id, 'submitted_at' => now()]);
        app(VerificationService::class)->review($businessRequest, $admin, VerificationStatus::REJECTED, 'private reason');

        $userNotifications = $this->actingAs($user)->getJson('/api/v1/notifications')->assertJsonPath('data.0.type', 'verification_approved');
        $this->assertStringNotContainsString('private reason', $userNotifications->getContent());
        $this->actingAs($owner)->getJson('/api/v1/notifications')->assertJsonPath('data.0.type', 'verification_rejected')->assertJsonMissingPath('data.0.actor.id');
    }

    public function test_notification_endpoints_require_authentication_and_cursor_paginate(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
        $user = User::factory()->create();
        foreach (range(1, 21) as $_) {
            Notification::create(['recipient_user_id' => $user->id, 'type' => 'followed', 'data' => []]);
        }
        $response = $this->actingAs($user)->getJson('/api/v1/notifications?per_page=20')->assertOk();
        $response->assertJsonCount(20, 'data')->assertJsonPath('meta.per_page', 20);
        $this->assertNotNull($response->json('meta.next_cursor'));
    }
}
