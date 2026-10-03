<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Enums\BusinessStatus;
use App\Enums\ReelStatus;
use App\Enums\ReportStatus;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Reel;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_report_each_supported_public_target_and_reporter_is_session_derived(): void
    {
        $reporter = User::factory()->create();
        $targetUser = User::factory()->create();
        $business = Business::factory()->create();
        $post = Post::factory()->create();
        $comment = Comment::factory()->create(['post_id' => $post->id]);
        $reel = Reel::create(['author_type' => 'user', 'author_id' => $targetUser->id, 'created_by_user_id' => $targetUser->id, 'status' => ReelStatus::PUBLISHED, 'published_at' => now()]);

        foreach ([['user', $targetUser->id], ['business', $business->id], ['post', $post->id], ['comment', $comment->id], ['reel', $reel->id]] as [$type, $id]) {
            $this->actingAs($reporter)->postJson('/api/v1/reports', ['target_type' => $type, 'target_id' => $id, 'reason' => 'spam'])->assertCreated();
        }

        $this->assertDatabaseCount('reports', 5);
        $this->assertDatabaseHas('reports', ['reporter_user_id' => $reporter->id, 'status' => ReportStatus::PENDING->value]);
    }

    public function test_report_validation_self_report_duplicates_and_other_details(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['created_by' => $user->id, 'author_id' => $user->id]);
        $this->actingAs($user)->postJson('/api/v1/reports', ['target_type' => 'user', 'target_id' => $user->id, 'reason' => 'spam'])->assertUnprocessable();
        $this->actingAs($user)->postJson('/api/v1/reports', ['target_type' => 'post', 'target_id' => $post->id, 'reason' => 'spam'])->assertUnprocessable();

        $other = User::factory()->create();
        $payload = ['target_type' => 'user', 'target_id' => $other->id, 'reason' => 'other'];
        $this->actingAs($user)->postJson('/api/v1/reports', $payload)->assertUnprocessable();
        $this->actingAs($user)->postJson('/api/v1/reports', $payload + ['details' => str_repeat('x', 2001)])->assertUnprocessable();
        $this->actingAs($user)->postJson('/api/v1/reports', $payload + ['details' => 'Specific concern'])->assertCreated();
        $this->actingAs($user)->postJson('/api/v1/reports', ['target_type' => 'user', 'target_id' => $other->id, 'reason' => 'spam'])->assertConflict();
    }

    public function test_admin_can_dismiss_and_action_reports_but_business_admin_cannot_review(): void
    {
        $reporter = User::factory()->create();
        $admin = User::factory()->create(['is_system_admin' => true]);
        $businessAdmin = User::factory()->create();
        $business = Business::factory()->create();
        BusinessMember::create(['business_id' => $business->id, 'user_id' => $businessAdmin->id, 'role' => BusinessRole::ADMIN]);
        $post = Post::factory()->create();
        $report = Report::create(['reporter_user_id' => $reporter->id, 'target_type' => 'post', 'target_id' => $post->id, 'reason' => 'spam', 'status' => ReportStatus::PENDING]);

        $this->actingAs($businessAdmin)->getJson('/api/v1/admin/reports')->assertForbidden();
        $this->actingAs($admin)->patchJson("/api/v1/admin/reports/{$report->id}", ['action' => 'dismiss', 'resolution' => 'No violation'])->assertOk();
        $this->assertDatabaseHas('reports', ['id' => $report->id, 'status' => 'dismissed']);
        $this->assertDatabaseHas('moderation_audit_logs', ['report_id' => $report->id, 'action' => 'report_dismissed']);
        $this->actingAs($admin)->patchJson("/api/v1/admin/reports/{$report->id}", ['action' => 'dismiss', 'resolution' => 'Again'])->assertConflict();
    }

    public function test_admin_actions_hide_content_and_suspend_accounts_with_audit(): void
    {
        $reporter = User::factory()->create();
        $admin = User::factory()->create(['is_system_admin' => true]);
        $targets = [
            ['type' => 'post', 'model' => Post::factory()->create()],
            ['type' => 'user', 'model' => User::factory()->create()],
            ['type' => 'business', 'model' => Business::factory()->create()],
        ];
        foreach ($targets as $item) {
            $report = Report::create(['reporter_user_id' => $reporter->id, 'target_type' => $item['type'], 'target_id' => $item['model']->id, 'reason' => 'spam', 'status' => ReportStatus::PENDING]);
            $action = $item['type'] === 'post' ? 'content_removed' : ($item['type'] === 'user' ? 'account_suspended' : 'business_suspended');
            $this->actingAs($admin)->patchJson("/api/v1/admin/reports/{$report->id}", ['action' => $action, 'resolution' => 'Action taken'])->assertOk();
            $this->assertDatabaseHas('reports', ['id' => $report->id, 'status' => 'actioned', 'moderation_action' => $action]);
            $this->assertDatabaseHas('moderation_audit_logs', ['report_id' => $report->id]);
        }
        $this->assertSoftDeleted('posts', ['id' => $targets[0]['model']->id]);
        $this->assertSame('suspended', $targets[1]['model']->fresh()->account_status->value);
        $this->assertSame(BusinessStatus::SUSPENDED, $targets[2]['model']->fresh()->status);
    }

    public function test_admin_list_is_paginated_filtered_and_mixed_targets_are_safe(): void
    {
        $reporter = User::factory()->create();
        $admin = User::factory()->create(['is_system_admin' => true]);
        foreach (range(1, 21) as $i) {
            Report::create(['reporter_user_id' => $reporter->id, 'target_type' => 'user', 'target_id' => User::factory()->create()->id, 'reason' => $i % 2 ? 'spam' : 'harassment', 'status' => ReportStatus::PENDING]);
        }
        $response = $this->actingAs($admin)->getJson('/api/v1/admin/reports?per_page=20&status=pending&target_type=user&reason=spam')->assertOk();
        $response->assertJsonCount(11, 'data')->assertJsonPath('meta.per_page', 20);
    }

    public function test_reporter_resource_does_not_expose_moderator_fields_and_missing_target_is_safe(): void
    {
        $reporter = User::factory()->create();
        $admin = User::factory()->create(['is_system_admin' => true]);
        $target = Post::factory()->create();
        $report = Report::create(['reporter_user_id' => $reporter->id, 'target_type' => 'post', 'target_id' => $target->id, 'reason' => 'spam', 'status' => ReportStatus::PENDING]);
        $target->delete();
        $this->actingAs($reporter)->getJson('/api/v1/me/reports')->assertOk()->assertJsonMissingPath('data.0.reviewed_by_user_id');
        $this->actingAs($admin)->getJson("/api/v1/admin/reports/{$report->id}")->assertOk()->assertJsonPath('data.target', null);
    }
}
