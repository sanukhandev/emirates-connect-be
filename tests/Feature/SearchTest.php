<?php

namespace Tests\Feature;

use App\Enums\BusinessStatus;
use App\Enums\Emirate;
use App\Enums\Industry;
use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_search_public_users_and_businesses_with_deterministic_ranking(): void
    {
        $exact = $this->user('Sanu Khan', 'Architect');
        $prefix = $this->user('Sanu Karim', 'Architect');
        $hidden = $this->user('Sanu Hidden', 'Architect', UserStatus::SUSPENDED);
        $business = Business::factory()->create([
            'name' => 'Sanu Technologies', 'slug' => 'sanu-technologies',
            'description' => 'Software and logistics',
        ]);

        $response = $this->getJson('/api/v1/search?q=Sanu%20Khan')->assertOk();
        $response->assertJsonPath('data.0.type', 'user')
            ->assertJsonPath('data.0.id', $exact->id)
            ->assertJsonMissing(['id' => $hidden->id]);
        $response->assertJsonMissingPath('data.0.email');
        $partial = $this->getJson('/api/v1/search?q=%20%20Sanu%20%20')->assertOk();
        $partial->assertJsonPath('data.0.display_name', $prefix->profile->display_name)
            ->assertJsonFragment(['type' => 'business', 'id' => $business->id]);
    }

    public function test_filters_and_type_endpoints_are_validated(): void
    {
        $verified = $this->user('Verified Engineer', 'Engineer', UserStatus::ACTIVE, true);
        $this->user('Other Engineer', 'Engineer');
        $business = Business::factory()->create([
            'name' => 'Dubai Logistics', 'industry' => Industry::LOGISTICS,
            'emirate' => Emirate::DUBAI,
            'verification_status' => VerificationStatus::APPROVED,
        ]);

        $this->getJson('/api/v1/search/users?industry='.Industry::TECHNOLOGY->value.'&verified=true')
            ->assertOk()->assertJsonPath('data.0.id', $verified->id);
        $this->getJson('/api/v1/search/businesses?emirate='.Emirate::DUBAI->value.'&verified=true')
            ->assertOk()->assertJsonPath('data.0.id', $business->id);
        $this->getJson('/api/v1/search?type=posts')->assertUnprocessable();
        $this->getJson('/api/v1/search?industry=unknown')->assertUnprocessable();
        $this->getJson('/api/v1/search?per_page=51')->assertUnprocessable();
    }

    public function test_filter_only_search_paginates_and_hides_inactive_businesses(): void
    {
        foreach (range(1, 21) as $number) {
            $this->user("Technology {$number}", 'Founder');
        }

        $inactive = Business::factory()->create(['name' => 'Hidden Technology', 'status' => BusinessStatus::INACTIVE]);
        $this->getJson('/api/v1/search?industry='.Industry::TECHNOLOGY->value.'&per_page=20')
            ->assertOk()->assertJsonPath('meta.per_page', 20)->assertJsonPath('meta.total', 21);
        $this->assertFalse(collect($this->getJson('/api/v1/search?industry='.Industry::TECHNOLOGY->value)->json('data'))
            ->contains(fn (array $item): bool => $item['type'] === 'business' && $item['id'] === $inactive->id));
    }

    public function test_special_queries_are_bound_and_short_queries_are_rejected(): void
    {
        $this->user('100%_safe', 'Founder');

        $this->getJson('/api/v1/search/users?q=100%25_')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/search?q=' OR 1=1 --")->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/search?q=a')->assertUnprocessable();
    }

    private function user(string $displayName, string $headline, UserStatus $status = UserStatus::ACTIVE, bool $verified = false): User
    {
        $user = User::factory()->create(['account_status' => $status]);
        $user->profile()->create([
            'display_name' => $displayName,
            'headline' => $headline,
            'industry' => Industry::TECHNOLOGY,
            'emirate' => Emirate::DUBAI,
            'verification_status' => $verified ? VerificationStatus::APPROVED : VerificationStatus::NOT_SUBMITTED,
        ]);

        return $user->load('profile');
    }
}
