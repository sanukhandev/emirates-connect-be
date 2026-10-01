<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Enums\BusinessStatus;
use App\Enums\UserStatus;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FollowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_follow_is_directional_idempotent_and_counts(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($actor)->putJson("/api/v1/users/{$target->id}/follow")->assertOk();
        $this->actingAs($actor)->putJson("/api/v1/users/{$target->id}/follow")->assertOk();

        $this->assertDatabaseCount('follows', 1);
        $this->getJson("/api/v1/users/{$target->id}")
            ->assertOk()
            ->assertJsonPath('data.followers_count', 1)
            ->assertJsonPath('data.is_following', true);
        $this->getJson("/api/v1/users/{$actor->id}")
            ->assertOk()
            ->assertJsonPath('data.following_count', 1);
        $this->getJson("/api/v1/users/{$actor->id}/followers")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($actor)->deleteJson("/api/v1/users/{$target->id}/follow")->assertNoContent();
        $this->actingAs($actor)->deleteJson("/api/v1/users/{$target->id}/follow")->assertNoContent();
        $this->assertDatabaseCount('follows', 0);
    }

    public function test_self_follow_and_hidden_targets_are_rejected(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor)->putJson("/api/v1/users/{$actor->id}/follow")->assertStatus(422);

        foreach ([UserStatus::SUSPENDED, UserStatus::DISABLED] as $status) {
            $target = User::factory()->create(['account_status' => $status]);
            $this->actingAs($actor)->putJson("/api/v1/users/{$target->id}/follow")->assertNotFound();
            $this->getJson("/api/v1/users/{$target->id}/followers")->assertNotFound();
        }
    }

    public function test_business_follow_is_idempotent_and_membership_independent(): void
    {
        $owner = User::factory()->create();
        $follower = User::factory()->create();
        $business = $this->business($owner);

        $this->actingAs($follower)->putJson("/api/v1/businesses/{$business->slug}/follow")->assertOk();
        $this->actingAs($follower)->putJson("/api/v1/businesses/{$business->slug}/follow")->assertOk();

        $this->assertDatabaseCount('follows', 1);
        $this->getJson("/api/v1/businesses/{$business->slug}")
            ->assertOk()
            ->assertJsonPath('data.followers_count', 1)
            ->assertJsonPath('data.is_following', true);
        $this->getJson("/api/v1/users/{$follower->id}")
            ->assertOk()
            ->assertJsonPath('data.following_count', 1);

        $this->actingAs($follower)->deleteJson("/api/v1/businesses/{$business->slug}/follow")->assertNoContent();
        $this->actingAs($follower)->deleteJson("/api/v1/businesses/{$business->slug}/follow")->assertNoContent();
    }

    public function test_following_and_follower_lists_are_paginated_and_normalized(): void
    {
        $actor = User::factory()->create();
        $userTarget = User::factory()->create();
        $business = $this->business(User::factory()->create());
        $businessFollower = User::factory()->create();

        $this->actingAs($actor)->putJson("/api/v1/users/{$userTarget->id}/follow")->assertOk();
        $this->actingAs($actor)->putJson("/api/v1/businesses/{$business->slug}/follow")->assertOk();
        $this->actingAs($businessFollower)->putJson("/api/v1/businesses/{$business->slug}/follow")->assertOk();

        $this->getJson("/api/v1/users/{$actor->id}/following")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', 'business')
            ->assertJsonPath('data.1.type', 'user')
            ->assertJsonMissingPath('data.0.email');
        $this->getJson("/api/v1/businesses/{$business->slug}/followers")
            ->assertOk()
            ->assertJsonPath('data.0.id', $businessFollower->id)
            ->assertJsonMissingPath('data.0.email');
        $this->getJson("/api/v1/users/{$userTarget->id}/followers")
            ->assertOk()
            ->assertJsonPath('data.0.id', $actor->id);
    }

    public function test_business_status_and_actor_status_are_enforced(): void
    {
        $actor = User::factory()->create();
        foreach ([BusinessStatus::INACTIVE, BusinessStatus::SUSPENDED] as $status) {
            $business = $this->business(User::factory()->create(), $status);
            $this->actingAs($actor)->putJson("/api/v1/businesses/{$business->slug}/follow")->assertNotFound();
            $this->getJson("/api/v1/businesses/{$business->slug}/followers")->assertNotFound();
        }

        $target = User::factory()->create();
        $actor->update(['account_status' => UserStatus::SUSPENDED]);
        $this->actingAs($actor)->putJson("/api/v1/users/{$target->id}/follow")->assertForbidden();
        $this->actingAs($actor)->deleteJson("/api/v1/users/{$target->id}/follow")->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->putJson("/api/v1/users/{$target->id}/follow")->assertUnauthorized();
    }

    public function test_cross_type_targets_are_distinct_and_mutual_follows_are_directional(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $business = $this->business(User::factory()->create());

        $this->actingAs($first)->putJson("/api/v1/users/{$second->id}/follow")->assertOk();
        $this->actingAs($second)->putJson("/api/v1/users/{$first->id}/follow")->assertOk();
        $this->actingAs($first)->putJson("/api/v1/businesses/{$business->slug}/follow")->assertOk();

        $this->assertDatabaseCount('follows', 3);
        $this->assertSame(
            2,
            Follow::query()->where('follower_user_id', $first->id)->count()
        );
        $this->getJson("/api/v1/users/{$second->id}")
            ->assertOk()
            ->assertJsonPath('data.is_following', true);
    }

    private function business(User $owner, BusinessStatus $status = BusinessStatus::ACTIVE): Business
    {
        $business = Business::factory()->create([
            'created_by' => $owner->id,
            'status' => $status,
        ]);

        BusinessMember::create([
            'business_id' => $business->id,
            'user_id' => $owner->id,
            'role' => BusinessRole::OWNER,
        ]);

        return $business;
    }
}
