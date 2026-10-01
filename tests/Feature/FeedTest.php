<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Enums\BusinessStatus;
use App\Enums\UserStatus;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_requires_an_active_authenticated_account(): void
    {
        $this->getJson('/api/v1/feed')->assertUnauthorized();

        foreach ([UserStatus::SUSPENDED, UserStatus::DISABLED] as $status) {
            $user = User::factory()->create(['account_status' => $status]);

            $this->actingAs($user, 'sanctum')->getJson('/api/v1/feed')->assertForbidden();
        }
    }

    public function test_feed_returns_mixed_published_authors_in_deterministic_order(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create();
        $business = $this->business($viewer);
        $time = Carbon::parse('2026-01-01 12:00:00');

        $userPost = Post::factory()->create([
            'author_type' => 'user',
            'author_id' => $author->id,
            'created_by' => $author->id,
            'published_at' => $time,
        ]);
        $businessPost = Post::factory()->forBusiness($business, $viewer)->create(['published_at' => $time]);
        $newer = Post::factory()->create([
            'author_type' => 'user',
            'author_id' => $author->id,
            'created_by' => $author->id,
            'published_at' => $time->copy()->addMinute(),
        ]);

        $response = $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/feed');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $businessPost->id > $userPost->id ? $businessPost->id : $userPost->id)
            ->assertJsonPath('data.0.author.type', 'user')
            ->assertJsonPath('data.1.author.type', $businessPost->id > $userPost->id ? 'business' : 'user')
            ->assertJsonMissingPath('data.0.created_by')
            ->assertJsonMissingPath('data.0.author.email')
            ->assertJsonMissingPath('data.0.media.0.path');
    }

    public function test_feed_excludes_drafts_deleted_posts_and_ineligible_authors(): void
    {
        $viewer = User::factory()->create();
        $activeAuthor = User::factory()->create();
        $activePost = Post::factory()->create([
            'author_type' => 'user',
            'author_id' => $activeAuthor->id,
            'created_by' => $activeAuthor->id,
        ]);
        Post::factory()->draft()->create([
            'author_type' => 'user',
            'author_id' => $activeAuthor->id,
            'created_by' => $activeAuthor->id,
        ]);
        $deleted = Post::factory()->create([
            'author_type' => 'user',
            'author_id' => $activeAuthor->id,
            'created_by' => $activeAuthor->id,
        ]);
        $deleted->delete();

        foreach ([UserStatus::SUSPENDED, UserStatus::DISABLED] as $status) {
            $user = User::factory()->create(['account_status' => $status]);
            Post::factory()->create([
                'author_type' => 'user',
                'author_id' => $user->id,
                'created_by' => $user->id,
            ]);
        }

        foreach ([BusinessStatus::INACTIVE, BusinessStatus::SUSPENDED] as $status) {
            $business = $this->business($viewer, $status);
            Post::factory()->forBusiness($business, $viewer)->create();
        }

        $response = $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/feed');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame([$activePost->id], collect($response->json('data'))->pluck('id')->filter(fn (int $id): bool => $id === $activePost->id)->values()->all());
        $this->assertStringNotContainsString('Draft', $response->getContent());
    }

    public function test_feed_cursor_pagination_is_stable_and_has_no_duplicates(): void
    {
        $viewer = User::factory()->create();
        $time = Carbon::parse('2026-02-01 12:00:00');
        $posts = collect(range(1, 5))->map(function (int $number) use ($viewer, $time): Post {
            return Post::factory()->create([
                'author_type' => 'user',
                'author_id' => $viewer->id,
                'created_by' => $viewer->id,
                'body' => 'Feed post '.$number,
                'published_at' => $time,
            ]);
        });

        $first = $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/feed?per_page=2')->assertOk();
        $firstIds = collect($first->json('data'))->pluck('id')->all();
        $cursor = $first->json('meta.next_cursor');

        $newer = Post::factory()->create([
            'author_type' => 'user',
            'author_id' => $viewer->id,
            'created_by' => $viewer->id,
            'published_at' => $time->copy()->addMinute(),
        ]);
        $second = $this->getJson('/api/v1/feed?per_page=2&cursor='.urlencode($cursor))->assertOk();
        $secondIds = collect($second->json('data'))->pluck('id')->all();

        $this->assertCount(2, $firstIds);
        $this->assertCount(2, $secondIds);
        $this->assertEmpty(array_intersect($firstIds, $secondIds));
        $this->assertNotContains($newer->id, $secondIds);
        $this->assertSame($posts->sortByDesc('id')->pluck('id')->slice(0, 4)->values()->all(), array_merge($firstIds, $secondIds));
    }

    public function test_feed_validates_page_size_and_cursor_and_returns_empty_collection(): void
    {
        $viewer = User::factory()->create();

        $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/feed?per_page=0')->assertUnprocessable();
        $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/feed?per_page=51')->assertUnprocessable();
        $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/feed?cursor=invalid')->assertUnprocessable();
        $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/feed')->assertOk()->assertJsonCount(0, 'data');
    }

    private function business(User $owner, BusinessStatus $status = BusinessStatus::ACTIVE): Business
    {
        $business = Business::factory()->create(['created_by' => $owner->id, 'status' => $status]);
        BusinessMember::create([
            'business_id' => $business->id,
            'user_id' => $owner->id,
            'role' => BusinessRole::OWNER,
        ]);

        return $business;
    }
}
