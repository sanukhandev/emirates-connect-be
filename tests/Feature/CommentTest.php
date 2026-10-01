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

class CommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_comment_reply_edit_and_soft_delete_without_impersonation(): void
    {
        $owner = User::factory()->create();
        $commenter = User::factory()->create();
        $other = User::factory()->create();
        $post = Post::factory()->for($owner, 'author')->create(['created_by' => $owner->id]);

        $comment = $this->actingAs($commenter, 'sanctum')->postJson('/api/v1/posts/'.$post->id.'/comments', [
            'author_type' => 'user', 'author_id' => $other->id, 'body' => '  Useful insight.  ',
        ])->assertCreated()->assertJsonPath('data.author.id', $commenter->id)->json('data');
        $commentModel = Comment::findOrFail($comment['id']);
        $this->assertSame($commenter->id, $commentModel->created_by);

        $reply = $this->actingAs($other, 'sanctum')->postJson('/api/v1/comments/'.$commentModel->id.'/replies', [
            'author_type' => 'user', 'body' => 'Agreed.',
        ])->assertCreated()->json('data');
        $replyModel = Comment::findOrFail($reply['id']);

        $this->actingAs($commenter, 'sanctum')->patchJson('/api/v1/comments/'.$commentModel->id, ['body' => 'Updated.'])
            ->assertOk()->assertJsonPath('data.body', 'Updated.')
            ->assertJsonMissingPath('data.created_by');
        $this->actingAs($other, 'sanctum')->patchJson('/api/v1/comments/'.$commentModel->id, ['body' => 'Nope'])->assertForbidden();
        $this->actingAs($other, 'sanctum')->postJson('/api/v1/comments/'.$replyModel->id.'/replies', [
            'author_type' => 'user', 'body' => 'Too deep.',
        ])->assertForbidden();
        $this->actingAs($commenter, 'sanctum')->deleteJson('/api/v1/comments/'.$commentModel->id)->assertNoContent();
        $this->assertSoftDeleted('comments', ['id' => $commentModel->id]);
        $this->getJson('/api/v1/posts/'.$post->id.'/comments')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($other, 'sanctum')->postJson('/api/v1/comments/'.$commentModel->id.'/replies', [
            'author_type' => 'user', 'body' => 'No parent.',
        ])->assertNotFound();
    }

    public function test_business_roles_can_comment_and_former_member_loses_access(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $editor = User::factory()->create();
        $outsider = User::factory()->create();
        $business = Business::factory()->create(['created_by' => $owner->id]);
        foreach ([[$owner, BusinessRole::OWNER], [$admin, BusinessRole::ADMIN], [$editor, BusinessRole::EDITOR]] as [$user, $role]) {
            BusinessMember::create(['business_id' => $business->id, 'user_id' => $user->id, 'role' => $role]);
        }
        $post = Post::factory()->forBusiness($business, $owner)->create();

        foreach ([$owner, $admin, $editor] as $actor) {
            $this->actingAs($actor, 'sanctum')->postJson('/api/v1/posts/'.$post->id.'/comments', [
                'author_type' => 'business', 'business_id' => $business->id, 'body' => 'From '.$actor->id,
            ])->assertCreated()->assertJsonPath('data.author.type', 'business');
        }
        $this->actingAs($outsider, 'sanctum')->postJson('/api/v1/posts/'.$post->id.'/comments', [
            'author_type' => 'business', 'business_id' => $business->id, 'body' => 'Denied',
        ])->assertForbidden();

        $editorComment = Comment::where('author_type', 'business')->where('created_by', $editor->id)->firstOrFail();
        BusinessMember::where('business_id', $business->id)->where('user_id', $editor->id)->delete();
        $this->actingAs($editor, 'sanctum')->patchJson('/api/v1/comments/'.$editorComment->id, ['body' => 'Denied'])->assertForbidden();
        $this->actingAs($editor, 'sanctum')->deleteJson('/api/v1/comments/'.$editorComment->id)->assertForbidden();
    }

    public function test_comment_listing_is_paginated_ordered_and_hides_replies_of_deleted_parents(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->for($author, 'author')->create(['created_by' => $author->id]);
        $first = Comment::factory()->create(['post_id' => $post->id, 'created_at' => now()->subMinute()]);
        $second = Comment::factory()->create(['post_id' => $post->id, 'created_at' => now()]);
        Comment::factory()->reply($first)->create();
        Comment::factory()->reply($first)->create();

        $this->getJson('/api/v1/posts/'.$post->id.'/comments')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.0.replies_count', 2)
            ->assertJsonCount(2, 'data.0.replies');

        $first->delete();
        $this->getJson('/api/v1/posts/'.$post->id.'/comments')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $second->id);
    }

    public function test_invalid_bodies_and_hidden_posts_are_rejected(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user, 'author')->create(['created_by' => $user->id]);
        foreach (['', '   ', str_repeat('x', 2001)] as $body) {
            $this->actingAs($user, 'sanctum')->postJson('/api/v1/posts/'.$post->id.'/comments', [
                'author_type' => 'user', 'body' => $body,
            ])->assertUnprocessable();
        }

        $post->update(['status' => 'draft', 'published_at' => null]);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/posts/'.$post->id.'/comments', [
            'author_type' => 'user', 'body' => 'Hidden',
        ])->assertNotFound();
        $post->update(['status' => 'published', 'published_at' => now()]);
        $post->delete();
        $this->getJson('/api/v1/posts/'.$post->id.'/comments')->assertNotFound();

        $hiddenUser = User::factory()->create(['account_status' => UserStatus::SUSPENDED]);
        $hiddenPost = Post::factory()->for($hiddenUser, 'author')->create(['created_by' => $hiddenUser->id]);
        $this->getJson('/api/v1/posts/'.$hiddenPost->id.'/comments')->assertNotFound();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/posts/'.$hiddenPost->id.'/comments', [
            'author_type' => 'user', 'body' => 'Hidden',
        ])->assertNotFound();

        $business = Business::factory()->create(['created_by' => $user->id]);
        BusinessMember::create(['business_id' => $business->id, 'user_id' => $user->id, 'role' => BusinessRole::OWNER]);
        $businessPost = Post::factory()->forBusiness($business, $user)->create();
        $business->update(['status' => BusinessStatus::INACTIVE]);
        $this->getJson('/api/v1/posts/'.$businessPost->id.'/comments')->assertNotFound();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/posts/'.$businessPost->id.'/comments', [
            'author_type' => 'business', 'business_id' => $business->id, 'body' => 'Hidden',
        ])->assertNotFound();
    }
}
