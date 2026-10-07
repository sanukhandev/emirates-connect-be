<?php

namespace Tests\Feature;

use App\Enums\Emirate;
use App\Enums\Industry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_a_profile_and_me_includes_it(): void
    {
        $this->withHeader('Origin', 'http://localhost:4200')->postJson('/api/v1/auth/register', [
            'name' => 'Sanu Khan', 'email' => 'sanu@example.com',
            'password' => 'StrongPassword123!', 'password_confirmation' => 'StrongPassword123!',
        ])->assertCreated();

        $this->assertDatabaseHas('profiles', ['display_name' => 'Sanu Khan']);
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.profile.display_name', 'Sanu Khan');
    }

    public function test_profile_can_be_read_and_updated_by_its_owner(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/me/profile')->assertOk();
        $this->actingAs($user, 'sanctum')->patchJson('/api/v1/me/profile', [
            'display_name' => 'Public Sanu', 'industry' => Industry::TECHNOLOGY->value,
            'emirate' => Emirate::DUBAI->value, 'website_url' => 'https://example.com',
            'linkedin_url' => 'https://www.linkedin.com/in/sanu',
        ])->assertOk()->assertJsonPath('data.display_name', 'Public Sanu');
    }

    public function test_profile_validation_rejects_invalid_values_and_unsafe_urls(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')->patchJson('/api/v1/me/profile', [
            'industry' => 'unknown', 'emirate' => 'uae', 'website_url' => 'javascript:alert(1)',
            'linkedin_url' => 'https://example.com/person', 'bio' => str_repeat('x', 2001),
        ])->assertUnprocessable();
    }

    public function test_onboarding_requires_fields_and_is_idempotent(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/me/onboarding/complete', [])->assertUnprocessable();
        $payload = ['display_name' => 'Sanu', 'headline' => 'Founder', 'job_title' => 'CEO',
            'industry' => 'technology', 'emirate' => 'dubai'];
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/me/onboarding/complete', $payload)->assertOk();
        $first = $user->refresh()->profile->onboarding_completed_at;
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/me/onboarding/complete', $payload)->assertOk();
        $this->assertTrue($first->equalTo($user->refresh()->profile->onboarding_completed_at));
    }

    public function test_public_profile_hides_email_and_blocked_accounts(): void
    {
        $user = User::factory()->create();
        $this->getJson("/api/v1/users/{$user->id}")->assertOk()
            ->assertJsonMissingPath('data.email')->assertJsonMissingPath('data.password');
        $user->update(['account_status' => 'suspended']);
        $this->getJson("/api/v1/users/{$user->id}")->assertNotFound();
    }

    public function test_meta_endpoints_are_enum_backed(): void
    {
        $this->getJson('/api/v1/meta/industries')->assertOk()->assertJsonCount(count(Industry::cases()), 'data');
        $this->getJson('/api/v1/meta/emirates')->assertOk()->assertJsonCount(7, 'data');
    }

    public function test_avatar_replacement_and_deletion_are_safe(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $response = $this->actingAs($user, 'sanctum')->post('/api/v1/me/profile/avatar', [
            'avatar' => UploadedFile::fake()->create('avatar.jpg', 100, 'image/jpeg'),
        ])->assertOk();
        $old = $user->refresh()->profile->avatar_path;
        $this->actingAs($user, 'sanctum')->post('/api/v1/me/profile/avatar', [
            'avatar' => UploadedFile::fake()->create('avatar.png', 100, 'image/png'),
        ])->assertOk();
        $new = $user->refresh()->profile->avatar_path;
        $disk = Storage::disk('public');
        $disk->assertMissing($old);
        $disk->assertExists($new);
        $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/me/profile/avatar')->assertNoContent();
        $disk->assertMissing($new);
    }

    public function test_invalid_avatar_and_cover_are_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')->withHeaders(['Accept' => 'application/json'])->post('/api/v1/me/profile/avatar', [
            'avatar' => UploadedFile::fake()->create('bad.svg', 10, 'image/svg+xml'),
        ])->assertUnprocessable();
        $this->actingAs($user, 'sanctum')->post('/api/v1/me/profile/cover-image', [
            'cover_image' => UploadedFile::fake()->create('cover.jpg', 100, 'image/jpeg'),
        ])->assertOk();
        $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/me/profile/cover-image')->assertNoContent();
    }

    public function test_guests_cannot_mutate_profiles(): void
    {
        $this->getJson('/api/v1/me/profile')->assertUnauthorized();
        $this->patchJson('/api/v1/me/profile', [])->assertUnauthorized();
        $this->postJson('/api/v1/me/onboarding/complete', [])->assertUnauthorized();
    }
}
