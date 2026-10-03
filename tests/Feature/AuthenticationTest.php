<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_spa_registration_normalizes_email_hashes_password_and_creates_a_session(): void
    {
        Notification::fake();

        $response = $this->withHeader('Origin', 'http://localhost:4200')->postJson('/api/v1/auth/register', [
            'name' => 'Sanu Khan',
            'email' => '  SANU@example.com ',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.email', 'sanu@example.com')
            ->assertJsonPath('data.user.account_status', 'active')
            ->assertJsonMissingPath('data.token')
            ->assertJsonMissingPath('data.user.password');

        $user = User::firstOrFail();
        $this->assertTrue(Hash::check('StrongPassword123!', $user->password));
        $this->assertAuthenticatedAs($user, 'web');
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registration_rejects_duplicate_case_insensitive_email_and_weak_password(): void
    {
        User::factory()->create(['email' => 'sanu@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Sanu Khan',
            'email' => 'SANU@EXAMPLE.COM',
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_spa_login_creates_a_session_without_returning_a_token(): void
    {
        $user = User::factory()->create(['email' => 'sanu@example.com', 'password' => 'StrongPassword123!']);

        $this->withHeader('Origin', 'http://localhost:4200')->postJson('/api/v1/auth/login', [
            'email' => ' SANU@example.com ',
            'password' => 'StrongPassword123!',
        ])->assertOk()
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonMissingPath('data.token')
            ->assertJsonMissingPath('data.user.password');

        $this->assertAuthenticatedAs($user, 'web');

        $this->withHeader('Origin', 'http://localhost:4200')->postJson('/api/v1/auth/login', [
            'email' => 'sanu@example.com',
            'password' => 'WrongPassword123!',
        ])->assertUnauthorized()
            ->assertJson(['message' => 'Invalid credentials.']);
    }

    public function test_mobile_token_endpoint_returns_a_device_named_token(): void
    {
        $user = User::factory()->create(['email' => 'sanu@example.com', 'password' => 'StrongPassword123!']);

        $response = $this->postJson('/api/v1/auth/mobile/token', [
            'email' => ' SANU@example.com ',
            'password' => 'StrongPassword123!',
            'device_name' => "Sanu's iPhone",
        ]);

        $response->assertOk()->assertJsonPath('data.user.email', $user->email);
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertDatabaseHas('personal_access_tokens', ['name' => "Sanu's iPhone"]);
    }

    public function test_mobile_token_requires_a_device_name(): void
    {
        User::factory()->create(['email' => 'sanu@example.com', 'password' => 'StrongPassword123!']);

        $this->postJson('/api/v1/auth/mobile/token', [
            'email' => 'sanu@example.com',
            'password' => 'StrongPassword123!',
        ])->assertUnprocessable()->assertJsonValidationErrors(['device_name']);
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'sanu@example.com', 'password' => 'StrongPassword123!']);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'sanu@example.com',
                'password' => 'WrongPassword123!',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'sanu@example.com',
            'password' => 'WrongPassword123!',
        ])->assertTooManyRequests();
    }

    public function test_registration_is_rate_limited(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withHeader('Origin', 'http://localhost:4200')->postJson('/api/v1/auth/register', [
                'name' => 'Sanu Khan',
                'email' => "sanu{$attempt}@example.com",
                'password' => 'StrongPassword123!',
                'password_confirmation' => 'StrongPassword123!',
            ])->assertCreated();
        }

        $this->withHeader('Origin', 'http://localhost:4200')->postJson('/api/v1/auth/register', [
            'name' => 'Sanu Khan',
            'email' => 'sanu-six@example.com',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ])->assertTooManyRequests();
    }

    public function test_suspended_and_disabled_users_cannot_login_or_access_protected_routes(): void
    {
        foreach ([UserStatus::SUSPENDED, UserStatus::DISABLED] as $status) {
            $user = User::factory()->create([
                'email' => $status->value.'@example.com',
                'password' => 'StrongPassword123!',
                'account_status' => $status,
            ]);
            $token = $user->createToken('test')->plainTextToken;

            $this->postJson('/api/v1/auth/mobile/token', [
                'email' => $user->email,
                'password' => 'StrongPassword123!',
                'device_name' => 'test-device',
            ])->assertForbidden();

            $this->withHeader('Origin', 'http://localhost:4200')->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'StrongPassword123!',
            ])->assertForbidden();

            $this->withToken($token)->getJson('/api/v1/me')->assertForbidden();
        }
    }

    public function test_authenticated_user_can_view_and_update_their_account(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'sanu@example.com']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'sanu@example.com')
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');

        $this->withToken($token)->patchJson('/api/v1/me', [
            'name' => 'Updated Sanu',
            'email' => 'new@example.com',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Updated Sanu')
            ->assertJsonPath('data.email', 'new@example.com')
            ->assertJsonPath('data.email_verified_at', null);

        $this->assertNull($user->fresh()->email_verified_at);
        Notification::assertSentTo($user->fresh(), VerifyEmail::class);
    }

    public function test_guest_cannot_view_or_update_their_account(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->patchJson('/api/v1/me', ['name' => 'Nope'])->assertUnauthorized();
    }

    public function test_spa_session_can_view_the_current_account_and_logout(): void
    {
        $user = User::factory()->create(['email' => 'sanu@example.com']);

        $this->withHeader('Origin', 'http://localhost:4200')->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $this->assertAuthenticatedAs($user, 'web');
        $this->withHeader('Origin', 'http://localhost:4200')->getJson('/api/v1/me')->assertOk();
        $this->withHeader('Origin', 'http://localhost:4200')->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->assertGuest('web');
    }

    public function test_csrf_cookie_route_is_available_for_the_spa(): void
    {
        $this->get('/sanctum/csrf-cookie')->assertNoContent();
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = User::factory()->create();
        $firstToken = $user->createToken('api-session')->plainTextToken;
        $secondToken = $user->createToken('api-session')->plainTextToken;

        $this->withToken($firstToken)->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertNull(PersonalAccessToken::findToken($firstToken));
        Auth::forgetGuards();
        $this->withToken($firstToken)->getJson('/api/v1/me')->assertUnauthorized();
        $this->withToken($secondToken)->getJson('/api/v1/me')->assertOk();
    }

    public function test_forgot_password_response_does_not_reveal_account_existence(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'sanu@example.com']);

        $message = 'If an account exists for this email, password reset instructions will be sent.';

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'unknown@example.com'])
            ->assertOk()
            ->assertJson(['message' => $message]);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => ' SANU@example.com '])
            ->assertOk()
            ->assertJson(['message' => $message]);

        Notification::assertSentTo(User::firstOrFail(), ResetPassword::class);
    }

    public function test_password_reset_changes_password_and_revokes_tokens(): void
    {
        $user = User::factory()->create(['email' => 'sanu@example.com']);
        $oldToken = $user->createToken('api-session')->plainTextToken;
        $resetToken = Password::broker()->createToken($user);

        $this->withHeader('Origin', 'http://localhost:4200')->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $resetToken,
            'email' => 'SANU@example.com',
            'password' => 'NewStrongPassword123!',
            'password_confirmation' => 'NewStrongPassword123!',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewStrongPassword123!', $user->fresh()->password));
        Auth::forgetGuards();
        $this->withToken($oldToken)->getJson('/api/v1/me')->assertUnauthorized();
        $this->withHeader('Origin', 'http://localhost:4200')->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_email_verification_notification_and_signed_link_work(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/email/verification-notification')
            ->assertAccepted();
        Notification::assertSentTo($user, VerifyEmail::class);

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(10), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->getJson($url)->assertOk();
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_invalid_email_verification_signature_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $this->getJson('/api/v1/auth/verify-email/'.$user->id.'/invalid')->assertForbidden();
    }
}
