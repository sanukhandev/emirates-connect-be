<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Enums\BusinessStatus;
use App\Enums\ReelStatus;
use App\Enums\UserStatus;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\Reel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_reel_upload_publishes_safely_and_delete_cleans_media(): void
    {
        $this->fakeReelStorage();
        $user = User::factory()->create();

        $created = $this->actingAs($user, 'sanctum')->postJson('/api/v1/reels', [
            'author_type' => 'user', 'caption' => 'A useful professional reel.',
        ])->assertCreated()->assertJsonPath('data.status', ReelStatus::UPLOADING->value);
        $reel = Reel::findOrFail($created->json('data.id'));

        $this->getJson('/api/v1/reels')->assertOk()->assertJsonCount(0, 'data');
        $uploaded = $this->actingAs($user, 'sanctum')->post('/api/v1/reels/'.$reel->id.'/video', [
            'video' => UploadedFile::fake()->create('reel.mp4', 100, 'video/mp4'),
        ], ['Accept' => 'application/json']);

        $uploaded->assertOk()
            ->assertJsonPath('data.status', ReelStatus::PUBLISHED->value)
            ->assertJsonPath('data.author.type', 'user')
            ->assertJsonMissingPath('data.source_path')
            ->assertJsonMissingPath('data.source_disk')
            ->assertJsonMissingPath('data.created_by_user_id')
            ->assertJsonPath('data.playback_url', fn ($value): bool => is_string($value));

        $reel->refresh();
        Storage::disk('reels_source')->assertExists($reel->source_path);
        Storage::disk('reels_playback')->assertExists($reel->playback_path);
        $this->getJson('/api/v1/reels')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/users/'.$user->id.'/reels')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/reels/'.$reel->id)->assertOk();

        $this->actingAs($user, 'sanctum')->patchJson('/api/v1/reels/'.$reel->id, ['caption' => 'Updated caption.'])
            ->assertOk()->assertJsonPath('data.caption', 'Updated caption.');
        $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/reels/'.$reel->id)->assertNoContent();
        Storage::disk('reels_source')->assertMissing($reel->source_path);
        Storage::disk('reels_playback')->assertMissing($reel->playback_path);
        $this->assertSoftDeleted('reels', ['id' => $reel->id]);
        $this->getJson('/api/v1/reels')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/reels/'.$reel->id)->assertNotFound();
    }

    public function test_video_mime_and_size_are_validated(): void
    {
        $this->fakeReelStorage();
        $user = User::factory()->create();
        $reel = $this->createReel($user);

        $this->actingAs($user, 'sanctum')->post('/api/v1/reels/'.$reel->id.'/video', [
            'video' => UploadedFile::fake()->create('image.jpg', 100, 'image/jpeg'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->actingAs($user, 'sanctum')->post('/api/v1/reels/'.$reel->id.'/video', [
            'video' => UploadedFile::fake()->create('large.mp4', 102401, 'video/mp4'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertSame(ReelStatus::UPLOADING, $reel->refresh()->status);
    }

    public function test_business_owner_admin_and_editor_can_create_but_non_members_and_inactive_businesses_cannot(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $editor = User::factory()->create();
        $outsider = User::factory()->create();
        $business = $this->business($owner);
        BusinessMember::create(['business_id' => $business->id, 'user_id' => $admin->id, 'role' => BusinessRole::ADMIN]);
        BusinessMember::create(['business_id' => $business->id, 'user_id' => $editor->id, 'role' => BusinessRole::EDITOR]);

        foreach ([$owner, $admin, $editor] as $actor) {
            $this->actingAs($actor, 'sanctum')->postJson('/api/v1/reels', [
                'author_type' => 'business', 'business_id' => $business->id,
            ])->assertCreated();
        }
        $this->actingAs($outsider, 'sanctum')->postJson('/api/v1/reels', [
            'author_type' => 'business', 'business_id' => $business->id,
        ])->assertForbidden();

        $business->update(['status' => BusinessStatus::INACTIVE]);
        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/reels', [
            'author_type' => 'business', 'business_id' => $business->id,
        ])->assertForbidden();
    }

    public function test_former_business_member_loses_reel_mutation_access_and_user_cannot_impersonate(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $business = $this->business($owner);
        $membership = BusinessMember::create(['business_id' => $business->id, 'user_id' => $editor->id, 'role' => BusinessRole::EDITOR]);
        $reel = $this->createReel($editor, ['author_type' => 'business', 'business_id' => $business->id]);

        $membership->delete();
        $this->actingAs($editor, 'sanctum')->patchJson('/api/v1/reels/'.$reel->id, ['caption' => 'No longer allowed.'])->assertForbidden();
        $this->actingAs($editor, 'sanctum')->deleteJson('/api/v1/reels/'.$reel->id)->assertForbidden();
        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/reels', [
            'author_type' => 'user', 'author_id' => $editor->id,
        ])->assertUnprocessable();
    }

    public function test_public_visibility_excludes_processing_failed_deleted_and_hidden_authors(): void
    {
        $this->fakeReelStorage();
        $user = User::factory()->create();
        $reel = $this->createReel($user);
        $this->actingAs($user, 'sanctum')->post('/api/v1/reels/'.$reel->id.'/video', [
            'video' => UploadedFile::fake()->create('reel.mp4', 100, 'video/mp4'),
        ], ['Accept' => 'application/json'])->assertOk();

        $reel->update(['status' => ReelStatus::FAILED, 'processing_error' => 'private detail']);
        $this->getJson('/api/v1/reels')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/me/reels')
            ->assertOk()->assertJsonPath('data.0.processing_error', 'Video processing failed.')
            ->assertJsonMissingPath('data.0.source_path');

        $reel->update(['status' => ReelStatus::PUBLISHED, 'published_at' => now()]);
        $user->update(['account_status' => UserStatus::SUSPENDED]);
        $this->getJson('/api/v1/reels')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_feed_is_chronological_cursor_paginated_and_does_not_expose_private_fields(): void
    {
        $this->fakeReelStorage();
        $user = User::factory()->create();
        foreach (range(1, 3) as $number) {
            $reel = $this->createReel($user, ['caption' => 'Reel '.$number]);
            $this->actingAs($user, 'sanctum')->post('/api/v1/reels/'.$reel->id.'/video', [
                'video' => UploadedFile::fake()->create('reel-'.$number.'.mp4', 100, 'video/mp4'),
            ], ['Accept' => 'application/json'])->assertOk();
        }

        $first = $this->getJson('/api/v1/reels?per_page=2')->assertOk();
        $this->assertCount(2, $first->json('data'));
        $this->assertSame(
            collect($first->json('data'))->pluck('id')->sortDesc()->values()->all(),
            collect($first->json('data'))->pluck('id')->values()->all(),
        );
        $this->assertStringNotContainsString('source_path', $first->getContent());
        $this->assertStringNotContainsString('playback_path', $first->getContent());
        $cursor = $first->json('meta.next_cursor');
        $second = $this->getJson('/api/v1/reels?per_page=2&cursor='.urlencode($cursor))->assertOk();
        $this->assertEmpty(array_intersect(collect($first->json('data'))->pluck('id')->all(), collect($second->json('data'))->pluck('id')->all()));
    }

    public function test_suspended_actor_cannot_create_reels(): void
    {
        $user = User::factory()->create(['account_status' => UserStatus::SUSPENDED]);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/reels', ['author_type' => 'user'])->assertForbidden();
    }

    private function createReel(User $actor, array $data = []): Reel
    {
        $response = $this->actingAs($actor, 'sanctum')->postJson('/api/v1/reels', array_merge(['author_type' => 'user'], $data));

        $response->assertCreated();

        return Reel::findOrFail($response->json('data.id'));
    }

    private function business(User $owner): Business
    {
        $business = Business::factory()->create(['created_by' => $owner->id]);
        BusinessMember::create(['business_id' => $business->id, 'user_id' => $owner->id, 'role' => BusinessRole::OWNER]);

        return $business;
    }

    private function fakeReelStorage(): void
    {
        Storage::fake('reels_source');
        Storage::fake('reels_playback');
    }
}
