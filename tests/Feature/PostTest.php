<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Enums\BusinessStatus;
use App\Enums\PostStatus;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_create_text_post_without_impersonating_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/posts', [
            'author_type' => 'user',
            'author_id' => $other->id,
            'body' => 'A useful update for the UAE founder community.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.author.type', 'user')
            ->assertJsonPath('data.author.id', $user->id)
            ->assertJsonPath('data.status', PostStatus::PUBLISHED->value)
            ->assertJsonPath('data.published_at', fn ($value): bool => is_string($value));
        $this->assertDatabaseHas('posts', ['author_type' => 'user', 'author_id' => $user->id, 'created_by' => $user->id]);
    }

    public function test_business_owner_admin_and_editor_can_publish_but_non_member_cannot(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $editor = User::factory()->create();
        $outsider = User::factory()->create();
        $business = $this->business($owner);
        BusinessMember::create(['business_id' => $business->id, 'user_id' => $admin->id, 'role' => BusinessRole::ADMIN]);
        BusinessMember::create(['business_id' => $business->id, 'user_id' => $editor->id, 'role' => BusinessRole::EDITOR]);

        foreach ([$owner, $admin, $editor] as $actor) {
            $response = $this->actingAs($actor, 'sanctum')->postJson('/api/v1/posts', [
                'author_type' => 'business', 'business_id' => $business->id, 'body' => $actor->name.' published this.',
            ]);
            $response->assertCreated($actor->email.' '.$response->getContent());
        }

        $this->actingAs($outsider, 'sanctum')->postJson('/api/v1/posts', [
            'author_type' => 'business', 'business_id' => $business->id, 'body' => 'Not allowed.',
        ])->assertForbidden();

        $business->update(['status' => BusinessStatus::INACTIVE]);
        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/posts', [
            'author_type' => 'business', 'business_id' => $business->id, 'body' => 'Not allowed while inactive.',
        ])->assertForbidden();
    }

    public function test_empty_posts_and_unsafe_media_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/posts', ['author_type' => 'user'])->assertUnprocessable();
        $this->actingAs($user, 'sanctum')->post('/api/v1/posts', [
            'author_type' => 'user',
            'media' => [UploadedFile::fake()->create('file.svg', 10, 'image/svg+xml')],
        ], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_image_post_limits_media_and_hides_storage_paths(): void
    {
        Storage::fake(config('filesystems.default'));
        $user = User::factory()->create();
        $files = array_map(fn (string $name): UploadedFile => UploadedFile::fake()->create($name, 100, 'image/png'), ['one.png', 'two.png', 'three.png', 'four.png']);

        $response = $this->actingAs($user, 'sanctum')->post('/api/v1/posts', [
            'author_type' => 'user', 'media' => $files,
        ], ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonCount(4, 'data.media')->assertJsonMissingPath('data.media.0.path');
        $post = Post::latest('id')->firstOrFail();
        $this->assertCount(4, $post->media);
        foreach ($post->media as $media) {
            Storage::disk(config('filesystems.default'))->assertExists($media->path);
        }

        $this->actingAs($user, 'sanctum')->post('/api/v1/posts/'.$post->id.'/media', [
            'media' => UploadedFile::fake()->create('five.png', 100, 'image/png'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_draft_publishing_update_delete_and_media_idor_are_enforced(): void
    {
        Storage::fake(config('filesystems.default'));
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $post = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/posts', [
            'author_type' => 'user', 'body' => 'Draft copy.', 'status' => PostStatus::DRAFT->value,
        ])->assertCreated()->json('data');
        $postModel = Post::findOrFail($post['id']);

        $this->clearAuth();
        $this->getJson('/api/v1/posts/'.$postModel->id)->assertNotFound();
        $this->actingAs($owner, 'sanctum')->patchJson('/api/v1/posts/'.$postModel->id, ['status' => PostStatus::PUBLISHED->value])
            ->assertOk()->assertJsonPath('data.status', PostStatus::PUBLISHED->value);
        $this->actingAs($other, 'sanctum')->patchJson('/api/v1/posts/'.$postModel->id, ['body' => 'No'])->assertForbidden();

        $media = PostMedia::create(['post_id' => $postModel->id, 'type' => 'image', 'path' => 'posts/test.png', 'mime_type' => 'image/png', 'size' => 10, 'sort_order' => 0]);
        $foreignPost = Post::factory()->create(['author_id' => $other->id, 'created_by' => $other->id]);
        $foreignMedia = PostMedia::create(['post_id' => $foreignPost->id, 'type' => 'image', 'path' => 'posts/foreign.png', 'mime_type' => 'image/png', 'size' => 10, 'sort_order' => 0]);
        $this->actingAs($owner, 'sanctum')->deleteJson('/api/v1/posts/'.$postModel->id.'/media/'.$foreignMedia->id)->assertNotFound();
        $this->actingAs($owner, 'sanctum')->deleteJson('/api/v1/posts/'.$postModel->id.'/media/'.$media->id)->assertOk();
        $this->actingAs($owner, 'sanctum')->deleteJson('/api/v1/posts/'.$postModel->id)->assertNoContent();
        $this->getJson('/api/v1/posts/'.$postModel->id)->assertNotFound();
        $this->assertSoftDeleted('posts', ['id' => $postModel->id]);
    }

    public function test_public_and_authenticated_post_lists_filter_drafts_and_deleted_posts(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $published = Post::factory()->create(['author_type' => 'user', 'author_id' => $user->id, 'created_by' => $user->id]);
        Post::factory()->draft()->create(['author_type' => 'user', 'author_id' => $user->id, 'created_by' => $user->id]);
        Post::factory()->create(['author_type' => 'user', 'author_id' => $other->id, 'created_by' => $other->id]);
        $deleted = Post::factory()->create(['author_type' => 'user', 'author_id' => $user->id, 'created_by' => $user->id]);
        $deleted->delete();

        $this->getJson('/api/v1/users/'.$user->id.'/posts')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $published->id);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/me/posts')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_business_posts_are_public_only_while_business_is_active(): void
    {
        $owner = User::factory()->create();
        $business = $this->business($owner);
        $post = Post::factory()->forBusiness($business, $owner)->create();
        Post::factory()->forBusiness($business, $owner)->draft()->create();

        $this->getJson('/api/v1/businesses/'.$business->slug.'/posts')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $post->id);
        $business->update(['status' => BusinessStatus::SUSPENDED]);
        $this->getJson('/api/v1/businesses/'.$business->slug.'/posts')->assertNotFound();
        $this->getJson('/api/v1/posts/'.$post->id)->assertNotFound();
    }

    private function business(User $owner): Business
    {
        $business = Business::factory()->create(['created_by' => $owner->id]);
        BusinessMember::create(['business_id' => $business->id, 'user_id' => $owner->id, 'role' => BusinessRole::OWNER]);

        return $business;
    }

    private function clearAuth(): void
    {
        $this->app['auth']->forgetGuards();
    }
}
