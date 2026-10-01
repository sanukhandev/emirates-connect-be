<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Enums\BusinessStatus;
use App\Enums\Emirate;
use App\Enums\Industry;
use App\Enums\UserStatus;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BusinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_creation_assigns_owner_and_unique_stable_slug(): void
    {
        $owner = User::factory()->create();
        $payload = $this->businessPayload('Example Technologies');

        $first = $this->createBusiness($owner, $payload);
        $second = $this->createBusiness($owner, $payload);

        $this->assertSame('example-technologies', $first->slug);
        $this->assertSame('example-technologies-2', $second->slug);
        $this->assertDatabaseHas('business_members', [
            'business_id' => $first->id,
            'user_id' => $owner->id,
            'role' => BusinessRole::OWNER->value,
        ]);

        $this->actingAs($owner, 'sanctum')->patchJson('/api/v1/businesses/'.$first->slug, [
            'name' => 'Renamed Technologies',
        ])->assertOk()->assertJsonPath('data.slug', 'example-technologies');
    }

    public function test_business_creation_requires_active_authentication(): void
    {
        $this->postJson('/api/v1/businesses', $this->businessPayload())->assertUnauthorized();

        foreach ([UserStatus::SUSPENDED, UserStatus::DISABLED] as $status) {
            $user = User::factory()->create(['account_status' => $status]);
            $this->actingAs($user, 'sanctum')->postJson('/api/v1/businesses', $this->businessPayload())
                ->assertForbidden();
        }
    }

    public function test_business_validation_rejects_unknown_values_and_unsafe_urls(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/businesses', [
            'name' => 'Unsafe Business',
            'industry' => 'unknown',
            'emirate' => 'uae',
            'website_url' => 'javascript:alert(1)',
        ])->assertUnprocessable();
    }

    public function test_public_business_hides_internal_fields_and_inactive_businesses(): void
    {
        $owner = User::factory()->create();
        $business = $this->createBusiness($owner);

        $this->clearAuth();
        $this->getJson('/api/v1/businesses/'.$business->slug)
            ->assertOk()
            ->assertJsonMissingPath('data.created_by')
            ->assertJsonMissingPath('data.logo_path')
            ->assertJsonMissingPath('data.cover_image_path')
            ->assertJsonPath('data.current_user_role', null);

        $this->actingAs($owner, 'sanctum')->getJson('/api/v1/businesses/'.$business->slug)
            ->assertOk()->assertJsonPath('data.current_user_role', BusinessRole::OWNER->value);

        $business->update(['status' => BusinessStatus::INACTIVE]);
        $this->getJson('/api/v1/businesses/'.$business->slug)->assertNotFound();
        $business->update(['status' => BusinessStatus::SUSPENDED]);
        $this->getJson('/api/v1/businesses/'.$business->slug)->assertNotFound();
    }

    public function test_only_owner_and_admin_can_update_and_editor_cannot(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $editor = User::factory()->create();
        $outsider = User::factory()->create();
        $business = $this->createBusiness($owner);
        $this->addMember($owner, $business, $admin, BusinessRole::ADMIN);
        $this->addMember($owner, $business, $editor, BusinessRole::EDITOR);

        $this->actingAs($admin, 'sanctum')->patchJson('/api/v1/businesses/'.$business->slug, ['tagline' => 'Admin edit'])
            ->assertOk();
        $this->actingAs($editor, 'sanctum')->patchJson('/api/v1/businesses/'.$business->slug, ['tagline' => 'No'])
            ->assertForbidden();
        $this->actingAs($outsider, 'sanctum')->patchJson('/api/v1/businesses/'.$business->slug, ['tagline' => 'No'])
            ->assertForbidden();
    }

    public function test_membership_roles_and_duplicate_active_user_rules_are_enforced(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $editor = User::factory()->create();
        $newEditor = User::factory()->create();
        $business = $this->createBusiness($owner);

        $this->addMember($owner, $business, $admin, BusinessRole::ADMIN);
        $this->addMember($owner, $business, $editor, BusinessRole::EDITOR);

        $this->actingAs($admin, 'sanctum')->postJson($this->membersPath($business), [
            'user_id' => $newEditor->id, 'role' => BusinessRole::EDITOR->value,
        ])->assertCreated();
        $this->actingAs($admin, 'sanctum')->postJson($this->membersPath($business), [
            'user_id' => User::factory()->create()->id, 'role' => BusinessRole::ADMIN->value,
        ])->assertForbidden();
        $this->actingAs($editor, 'sanctum')->postJson($this->membersPath($business), [
            'user_id' => User::factory()->create()->id, 'role' => BusinessRole::EDITOR->value,
        ])->assertForbidden();
        $this->actingAs($owner, 'sanctum')->postJson($this->membersPath($business), [
            'user_id' => $editor->id, 'role' => BusinessRole::EDITOR->value,
        ])->assertUnprocessable();

        $member = $business->members()->where('user_id', $editor->id)->firstOrFail();
        $this->actingAs($owner, 'sanctum')->patchJson($this->memberPath($business, $member), [
            'role' => BusinessRole::ADMIN->value,
        ])->assertOk();
        $this->actingAs($admin, 'sanctum')->patchJson($this->memberPath($business, $member), [
            'role' => BusinessRole::ADMIN->value,
        ])->assertForbidden();
    }

    public function test_membership_listing_is_paginated_and_removal_respects_roles_and_idor(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $editor = User::factory()->create();
        $business = $this->createBusiness($owner);
        $otherBusiness = $this->createBusiness(User::factory()->create());
        $foreignMember = User::factory()->create();

        $this->addMember($owner, $business, $admin, BusinessRole::ADMIN);
        $this->addMember($owner, $business, $editor, BusinessRole::EDITOR);
        $this->addMember(User::query()->whereKey($otherBusiness->created_by)->firstOrFail(), $otherBusiness, $foreignMember, BusinessRole::EDITOR);

        $this->actingAs($admin, 'sanctum')->getJson($this->membersPath($business))->assertOk()
            ->assertJsonPath('meta.per_page', 20);
        $foreign = $otherBusiness->members()->where('user_id', $foreignMember->id)->firstOrFail();
        $this->actingAs($owner, 'sanctum')->deleteJson($this->memberPath($business, $foreign))->assertNotFound();
        $this->actingAs($admin, 'sanctum')->deleteJson($this->memberPath($business, $business->members()->where('user_id', $admin->id)->firstOrFail()))
            ->assertForbidden();
        $this->actingAs($owner, 'sanctum')->deleteJson($this->memberPath($business, $business->members()->where('user_id', $owner->id)->firstOrFail()))
            ->assertForbidden();
        $this->actingAs($admin, 'sanctum')->deleteJson($this->memberPath($business, $business->members()->where('user_id', $editor->id)->firstOrFail()))
            ->assertNoContent();
    }

    public function test_my_businesses_includes_each_membership_role_only(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $editor = User::factory()->create();
        $business = $this->createBusiness($owner);
        $adminBusiness = $this->createBusiness($admin, $this->businessPayload('Admin Business'));
        $editorBusiness = $this->createBusiness(User::factory()->create(), $this->businessPayload('Editor Business'));
        $this->addMember($owner, $business, $admin, BusinessRole::ADMIN);
        $this->addMember($owner, $business, $editor, BusinessRole::EDITOR);
        $this->addMember($editorBusiness->creator, $editorBusiness, $editor, BusinessRole::EDITOR);

        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/me/businesses')->assertOk()
            ->assertJsonFragment(['current_user_role' => BusinessRole::ADMIN->value])
            ->assertJsonFragment(['slug' => $adminBusiness->slug]);
        $this->actingAs($editor, 'sanctum')->getJson('/api/v1/me/businesses')->assertOk()
            ->assertJsonFragment(['current_user_role' => BusinessRole::EDITOR->value])
            ->assertJsonFragment(['slug' => $editorBusiness->slug]);
    }

    public function test_logo_and_cover_upload_replacement_and_deletion_are_safe(): void
    {
        $disk = config('filesystems.default');
        Storage::fake($disk);
        $owner = User::factory()->create();
        $business = $this->createBusiness($owner);

        $avatar = UploadedFile::fake()->create('logo.png', 100, 'image/png');
        $response = $this->actingAs($owner, 'sanctum')->post($this->mediaPath($business, 'logo'), ['logo' => $avatar]);
        $response->assertOk()->assertJsonMissingPath('data.logo_path');
        $oldLogo = $business->refresh()->logo_path;
        Storage::disk($disk)->assertExists($oldLogo);

        $this->actingAs($owner, 'sanctum')->withHeader('Accept', 'application/json')->post($this->mediaPath($business, 'logo'), [
            'logo' => UploadedFile::fake()->create('replacement.jpg', 100, 'image/jpeg'),
        ])->assertOk();
        $newLogo = $business->refresh()->logo_path;
        Storage::disk($disk)->assertMissing($oldLogo);
        $this->assertNotSame($oldLogo, $newLogo);
        $this->actingAs($owner, 'sanctum')->deleteJson($this->mediaPath($business, 'logo'))->assertNoContent();
        Storage::disk($disk)->assertMissing($newLogo);

        $this->actingAs($owner, 'sanctum')->post($this->mediaPath($business, 'cover-image'), [
            'cover_image' => UploadedFile::fake()->create('cover.jpg', 100, 'image/jpeg'),
        ])->assertOk();
        $oldCover = $business->refresh()->cover_image_path;
        $this->actingAs($owner, 'sanctum')->post($this->mediaPath($business, 'cover-image'), [
            'cover_image' => UploadedFile::fake()->create('replacement.webp', 100, 'image/webp'),
        ])->assertOk();
        $newCover = $business->refresh()->cover_image_path;
        Storage::disk($disk)->assertMissing($oldCover);
        $this->assertNotSame($oldCover, $newCover);
        $this->actingAs($owner, 'sanctum')->deleteJson($this->mediaPath($business, 'cover-image'))->assertNoContent();
        Storage::disk($disk)->assertMissing($newCover);
    }

    public function test_media_authorization_and_validation_are_enforced(): void
    {
        Storage::fake(config('filesystems.default'));
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $business = $this->createBusiness($owner);
        $this->addMember($owner, $business, $editor, BusinessRole::EDITOR);

        $this->actingAs($editor, 'sanctum')->post($this->mediaPath($business, 'logo'), [
            'logo' => UploadedFile::fake()->create('logo.png', 100, 'image/png'),
        ])->assertForbidden();
        $this->actingAs($owner, 'sanctum')->withHeader('Accept', 'application/json')->post($this->mediaPath($business, 'logo'), [
            'logo' => UploadedFile::fake()->create('bad.svg', 1, 'image/svg+xml'),
        ])->assertUnprocessable();
        $this->actingAs($owner, 'sanctum')->post($this->mediaPath($business, 'logo'), [
            'logo' => UploadedFile::fake()->create('large.png', 6000, 'image/png'),
        ])->assertUnprocessable();
        $this->actingAs($owner, 'sanctum')->withHeader('Accept', 'application/json')->post($this->mediaPath($business, 'cover-image'), [
            'cover_image' => UploadedFile::fake()->create('large.jpg', 9000, 'image/jpeg'),
        ])->assertUnprocessable();
    }

    public function test_only_owner_can_deactivate_business(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $business = $this->createBusiness($owner);
        $this->addMember($owner, $business, $admin, BusinessRole::ADMIN);

        $this->actingAs($admin, 'sanctum')->deleteJson('/api/v1/businesses/'.$business->slug)->assertForbidden();
        $this->actingAs($owner, 'sanctum')->deleteJson('/api/v1/businesses/'.$business->slug)->assertNoContent();
        $this->assertSame(BusinessStatus::INACTIVE, $business->refresh()->status);
        $this->getJson('/api/v1/businesses/'.$business->slug)->assertNotFound();
    }

    private function businessPayload(string $name = 'Sanu Consulting'): array
    {
        return [
            'name' => $name,
            'tagline' => 'Professional services',
            'description' => 'A business description.',
            'industry' => Industry::TECHNOLOGY->value,
            'emirate' => Emirate::DUBAI->value,
            'website_url' => 'https://example.com',
            'email' => 'hello@example.com',
            'phone' => '+971500000000',
        ];
    }

    private function createBusiness(User $user, ?array $payload = null): Business
    {
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/businesses', $payload ?? $this->businessPayload());
        $response->assertCreated();

        return Business::query()->where('slug', $response->json('data.slug'))->firstOrFail();
    }

    private function addMember(User $actor, Business $business, User $user, BusinessRole $role): BusinessMember
    {
        $response = $this->actingAs($actor, 'sanctum')->postJson($this->membersPath($business), [
            'user_id' => $user->id,
            'role' => $role->value,
        ]);
        $response->assertCreated();

        return BusinessMember::query()->findOrFail($response->json('data.id'));
    }

    private function membersPath(Business $business): string
    {
        return '/api/v1/businesses/'.$business->slug.'/members';
    }

    private function memberPath(Business $business, BusinessMember $member): string
    {
        return $this->membersPath($business).'/'.$member->id;
    }

    private function mediaPath(Business $business, string $kind): string
    {
        return '/api/v1/businesses/'.$business->slug.'/'.$kind;
    }

    private function clearAuth(): void
    {
        $this->app['auth']->forgetGuards();
    }
}
