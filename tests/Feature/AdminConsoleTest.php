<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Enums\BusinessStatus;
use App\Enums\PostStatus;
use App\Enums\ReelStatus;
use App\Enums\ReportStatus;
use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\ModerationAuditLog;
use App\Models\Post;
use App\Models\Reel;
use App\Models\Report;
use App\Models\User;
use App\Models\VerificationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminConsoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_mixed_user_and_business_verifications_are_loaded_without_morph_failure(): void
    {
        $admin = User::factory()->create(['is_system_admin' => true]);
        $user = User::factory()->create();
        $business = Business::factory()->create();
        VerificationRequest::create(['subject_type' => 'user', 'subject_id' => $user->id, 'status' => VerificationStatus::PENDING, 'submitted_by_user_id' => $user->id, 'submitted_at' => now()]);
        VerificationRequest::create(['subject_type' => 'business', 'subject_id' => $business->id, 'status' => VerificationStatus::PENDING, 'submitted_by_user_id' => $business->created_by, 'submitted_at' => now()->subMinute()]);

        $this->actingAs($admin)->getJson('/api/v1/admin/verifications?status=pending&per_page=20')
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.subject.type', 'user')->assertJsonPath('data.1.subject.type', 'business');
        $this->actingAs($admin)->getJson('/api/v1/admin/verifications?subject_type=business')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($admin)->getJson('/api/v1/admin/verifications/audit')->assertOk();
    }

    public function test_mixed_verification_loading_is_bounded_and_paginated(): void
    {
        $admin = User::factory()->create(['is_system_admin' => true]);
        for ($index = 0; $index < 12; $index++) {
            $user = User::factory()->create();
            VerificationRequest::create(['subject_type' => 'user', 'subject_id' => $user->id, 'status' => VerificationStatus::PENDING, 'submitted_by_user_id' => $user->id, 'submitted_at' => now()->subSeconds($index)]);
            $business = Business::factory()->create();
            VerificationRequest::create(['subject_type' => 'business', 'subject_id' => $business->id, 'status' => VerificationStatus::PENDING, 'submitted_by_user_id' => $business->created_by, 'submitted_at' => now()->subSeconds($index + 20)]);
        }

        DB::enableQueryLog();
        $response = $this->actingAs($admin)->getJson('/api/v1/admin/verifications?per_page=20')->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertJsonCount(20, 'data');
        $this->assertLessThan(12, $queries, 'Verification loading regressed to per-row queries.');
    }

    public function test_admin_dashboard_counts_are_scoped_and_operational(): void
    {
        $admin = User::factory()->create(['is_system_admin' => true]);
        User::factory()->create(['account_status' => UserStatus::SUSPENDED]);
        $business = Business::factory()->create(['status' => BusinessStatus::SUSPENDED]);
        $post = Post::factory()->create(['status' => PostStatus::PUBLISHED, 'published_at' => now()]);
        Reel::create(['author_type' => 'user', 'author_id' => $post->created_by, 'created_by_user_id' => $post->created_by, 'status' => ReelStatus::PUBLISHED, 'published_at' => now()]);
        VerificationRequest::create(['subject_type' => 'user', 'subject_id' => $post->created_by, 'status' => VerificationStatus::PENDING, 'submitted_by_user_id' => $post->created_by, 'submitted_at' => now()]);
        Report::create(['reporter_user_id' => $post->created_by, 'target_type' => 'business', 'target_id' => $business->id, 'reason' => 'spam', 'status' => ReportStatus::PENDING]);

        $this->actingAs($admin)->getJson('/api/v1/admin/dashboard')->assertOk()
            ->assertJsonPath('data.users.suspended', 1)
            ->assertJsonPath('data.businesses.suspended', 1)
            ->assertJsonPath('data.content.published_posts', 1)
            ->assertJsonPath('data.content.published_reels', 1)
            ->assertJsonPath('data.queues.pending_verifications', 1)
            ->assertJsonPath('data.queues.pending_reports', 1);
    }

    public function test_admin_user_and_business_lists_support_filters_and_safe_details(): void
    {
        $admin = User::factory()->create(['is_system_admin' => true]);
        $suspended = User::factory()->create(['account_status' => UserStatus::SUSPENDED]);
        $business = Business::factory()->create(['status' => BusinessStatus::ACTIVE]);
        BusinessMember::create(['business_id' => $business->id, 'user_id' => $suspended->id, 'role' => BusinessRole::OWNER]);

        $this->actingAs($admin)->getJson('/api/v1/admin/users?status=suspended')->assertOk()->assertJsonPath('data.0.id', $suspended->id)->assertJsonMissingPath('data.0.password');
        $this->actingAs($admin)->getJson('/api/v1/admin/users/'.$suspended->id)->assertOk()->assertJsonPath('data.id', $suspended->id);
        $this->actingAs($admin)->getJson('/api/v1/admin/businesses?status=active')->assertOk()->assertJsonPath('data.0.slug', $business->slug);
        $this->actingAs($admin)->getJson('/api/v1/admin/businesses/'.$business->id)->assertOk()->assertJsonPath('data.id', $business->id)->assertJsonMissingPath('data.logo_path');
    }

    public function test_admin_boundary_and_direct_suspension_are_safe_and_audited(): void
    {
        $admin = User::factory()->create(['is_system_admin' => true]);
        $normal = User::factory()->create();
        $businessAdmin = User::factory()->create();
        $businessEditor = User::factory()->create();
        $business = Business::factory()->create(['created_by' => $businessAdmin->id]);
        BusinessMember::create(['business_id' => $business->id, 'user_id' => $businessAdmin->id, 'role' => BusinessRole::OWNER]);
        BusinessMember::create(['business_id' => $business->id, 'user_id' => $businessEditor->id, 'role' => BusinessRole::EDITOR]);

        $guestStatus = $this->getJson('/api/v1/admin/dashboard')->status();
        $this->assertContains($guestStatus, [401, 403]);
        foreach ([$normal, $businessAdmin, $businessEditor] as $actor) {
            $this->actingAs($actor)->getJson('/api/v1/admin/dashboard')->assertForbidden();
            $this->actingAs($actor)->getJson('/api/v1/admin/reports')->assertForbidden();
            $this->actingAs($actor)->getJson('/api/v1/admin/verifications')->assertForbidden();
        }
        $this->actingAs($admin)->getJson('/api/v1/admin/reports?status=unknown')->assertUnprocessable();
        $this->actingAs($admin)->getJson('/api/v1/admin/verifications?subject_type=post')->assertUnprocessable();
        $this->actingAs(User::factory()->create(['is_system_admin' => true, 'account_status' => UserStatus::SUSPENDED]))->getJson('/api/v1/admin/dashboard')->assertForbidden();

        $this->actingAs($admin)->postJson('/api/v1/admin/users/'.$normal->id.'/suspend', ['reason' => 'Policy violation'])->assertOk()->assertJsonPath('data.account_status', 'suspended');
        $this->assertDatabaseHas('moderation_audit_logs', ['action' => 'user_suspended', 'target_id' => $normal->id, 'actor_user_id' => $admin->id]);
        $this->actingAs($admin)->postJson('/api/v1/admin/users/'.$admin->id.'/suspend', ['reason' => 'No'])->assertUnprocessable();
        $this->actingAs($admin)->postJson('/api/v1/admin/businesses/'.$business->id.'/suspend', ['reason' => 'Policy violation'])->assertOk()->assertJsonPath('data.status', 'suspended');
        $this->assertSame(2, ModerationAuditLog::query()->where('actor_user_id', $admin->id)->count());
    }
}
