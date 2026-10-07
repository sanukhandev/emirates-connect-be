<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventStoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_system_admin_can_create_events_and_users_can_rsvp(): void
    {
        $admin = User::factory()->create(['is_system_admin' => true]);
        $user = User::factory()->create();
        $payload = ['title' => 'UAE Founder Night', 'description' => 'Meet local founders.', 'venue' => 'Dubai', 'emirate' => 'dubai', 'starts_at' => now()->addWeek()->toISOString()];

        $this->actingAs($user)->postJson('/api/v1/admin/events', $payload)->assertForbidden();
        $this->actingAs($admin)->postJson('/api/v1/admin/events', $payload)->assertCreated()->assertJsonPath('data.title', $payload['title']);
        $event = Event::firstOrFail();
        $this->actingAs($user)->getJson('/api/v1/events')->assertOk()->assertJsonPath('data.0.id', $event->id);
        $this->actingAs($user)->postJson("/api/v1/events/{$event->id}/rsvp")->assertOk()->assertJsonPath('data.is_rsvped', true);
        $this->assertDatabaseHas('event_rsvps', ['event_id' => $event->id, 'user_id' => $user->id]);
    }

    public function test_user_can_create_expiring_story_with_supported_media(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $response = $this->actingAs($user)->post('/api/v1/stories', ['body' => 'Building in the UAE.', 'media' => UploadedFile::fake()->create('update.png', 10, 'image/png')]);
        $response->assertCreated()->assertJsonPath('data.body', 'Building in the UAE.')->assertJsonPath('data.user.id', $user->id);
        $story = Story::firstOrFail();
        $this->assertTrue($story->expires_at->isFuture());
        Storage::disk('public')->assertExists($story->media_path);
    }
}
