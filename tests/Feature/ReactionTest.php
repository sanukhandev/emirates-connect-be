<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Enums\BusinessStatus;
use App\Enums\UserStatus;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_reactions_are_idempotent_switchable_and_removable(): void
    {
        $author = User::factory()->create();
        $user = User::factory()->create();
        $other = User::factory()->create();
        $post = Post::factory()->for($author, 'author')->create(['created_by' => $author->id]);

        $this->getJson('/api/v1/posts/'.$post->id)->assertOk()
            ->assertJsonPath('data.reactions.total', 0)
            ->assertJsonPath('data.reactions.current_user', null)
            ->assertJsonPath('data.reactions.counts.like', 0);

        $this->actingAs($user, 'sanctum')->putJson('/api/v1/posts/'.$post->id.'/reaction', ['type' => 'like'])
            ->assertOk()->assertJsonPath('data.reactions.total', 1)
            ->assertJsonPath('data.reactions.counts.like', 1)
            ->assertJsonPath('data.reactions.current_user', 'like');
        $this->actingAs($user, 'sanctum')->putJson('/api/v1/posts/'.$post->id.'/reaction', ['type' => 'like'])->assertOk();
        $this->assertDatabaseCount('reactions', 1);

        $this->actingAs($other, 'sanctum')->putJson('/api/v1/posts/'.$post->id.'/reaction', ['type' => 'celebrate'])->assertOk();
        $this->actingAs($user, 'sanctum')->putJson('/api/v1/posts/'.$post->id.'/reaction', ['type' => 'celebrate'])
            ->assertJsonPath('data.reactions.total', 2)
            ->assertJsonPath('data.reactions.counts.like', 0)
            ->assertJsonPath('data.reactions.counts.celebrate', 2);

        $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/posts/'.$post->id.'/reaction')->assertNoContent();
        $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/posts/'.$post->id.'/reaction')->assertNoContent();
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/posts/'.$post->id)
            ->assertJsonPath('data.reactions.total', 1)
            ->assertJsonPath('data.reactions.current_user', null);
    }

    public function test_comment_and_reply_reactions_share_the_same_contract(): void
    {
        $author = User::factory()->create();
        $user = User::factory()->create();
        $post = Post::factory()->for($author, 'author')->create(['created_by' => $author->id]);
        $comment = Comment::factory()->create(['post_id' => $post->id]);
        $reply = Comment::factory()->reply($comment)->create();

        $this->actingAs($user, 'sanctum')->putJson('/api/v1/comments/'.$comment->id.'/reaction', ['type' => 'support'])
            ->assertOk()->assertJsonPath('data.reactions.counts.support', 1)
            ->assertJsonPath('data.reactions.current_user', 'support');
        $this->actingAs($user, 'sanctum')->putJson('/api/v1/comments/'.$reply->id.'/reaction', ['type' => 'insightful'])
            ->assertOk()->assertJsonPath('data.reactions.counts.insightful', 1)
            ->assertJsonPath('data.reactions.current_user', 'insightful');

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/posts/'.$post->id.'/comments')->assertOk()
            ->assertJsonPath('data.0.reactions.counts.support', 1)
            ->assertJsonPath('data.0.replies.0.reactions.current_user', 'insightful')
            ->assertJsonPath('data.0.replies.0.reactions.counts.insightful', 1);
        $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/comments/'.$reply->id.'/reaction')->assertNoContent();
    }

    public function test_business_posts_can_be_reacted_to_without_business_identity(): void
    {
        $owner = User::factory()->create();
        $visitor = User::factory()->create();
        $business = Business::factory()->create(['created_by' => $owner->id]);
        BusinessMember::create(['business_id' => $business->id, 'user_id' => $owner->id, 'role' => BusinessRole::OWNER]);
        $post = Post::factory()->forBusiness($business, $owner)->create();

        $this->actingAs($visitor, 'sanctum')->putJson('/api/v1/posts/'.$post->id.'/reaction', ['type' => 'celebrate'])
            ->assertOk()->assertJsonPath('data.reactions.current_user', 'celebrate');
        $this->assertDatabaseHas('reactions', [
            'user_id' => $visitor->id,
            'reactable_type' => 'post',
            'reactable_id' => $post->id,
        ]);
    }

    public function test_guest_invalid_and_inactive_target_reactions_are_rejected(): void
    {
        $author = User::factory()->create();
        $user = User::factory()->create();
        $post = Post::factory()->for($author, 'author')->create(['created_by' => $author->id]);

        $this->putJson('/api/v1/posts/'.$post->id.'/reaction', ['type' => 'like'])->assertUnauthorized();
        $this->actingAs($user, 'sanctum')->putJson('/api/v1/posts/'.$post->id.'/reaction', ['type' => 'love'])->assertUnprocessable();

        $post->update(['status' => 'draft', 'published_at' => null]);
        $this->actingAs($user, 'sanctum')->putJson('/api/v1/posts/'.$post->id.'/reaction', ['type' => 'like'])->assertNotFound();
        $post->update(['status' => 'published', 'published_at' => now()]);
        $post->delete();
        $this->actingAs($user, 'sanctum')->putJson('/api/v1/posts/'.$post->id.'/reaction', ['type' => 'like'])->assertNotFound();

        $hidden = User::factory()->create(['account_status' => UserStatus::SUSPENDED]);
        $hiddenPost = Post::factory()->for($hidden, 'author')->create(['created_by' => $hidden->id]);
        $this->actingAs($user, 'sanctum')->putJson('/api/v1/posts/'.$hiddenPost->id.'/reaction', ['type' => 'like'])->assertNotFound();

        $business = Business::factory()->create(['created_by' => $author->id, 'status' => BusinessStatus::INACTIVE]);
        BusinessMember::create(['business_id' => $business->id, 'user_id' => $author->id, 'role' => BusinessRole::OWNER]);
        $businessPost = Post::factory()->forBusiness($business, $author)->create();
        $this->actingAs($user, 'sanctum')->putJson('/api/v1/posts/'.$businessPost->id.'/reaction', ['type' => 'like'])->assertNotFound();
    }

    public function test_suspended_actor_cannot_mutate_reactions(): void
    {
        $author = User::factory()->create();
        $actor = User::factory()->create(['account_status' => UserStatus::SUSPENDED]);
        $post = Post::factory()->for($author, 'author')->create(['created_by' => $author->id]);

        $this->actingAs($actor, 'sanctum')->putJson('/api/v1/posts/'.$post->id.'/reaction', ['type' => 'like'])->assertForbidden();
    }

    public function test_reaction_response_does_not_expose_reactor_identity_or_internal_fields(): void
    {
        $author = User::factory()->create();
        $user = User::factory()->create();
        $post = Post::factory()->for($author, 'author')->create(['created_by' => $author->id]);

        $this->actingAs($user, 'sanctum')->putJson('/api/v1/posts/'.$post->id.'/reaction', ['type' => 'like'])
            ->assertJsonMissingPath('data.user_id')
            ->assertJsonMissingPath('data.created_by')
            ->assertJsonMissingPath('data.reactions.user_id')
            ->assertJsonMissingPath('data.reactions.email');
    }
}
