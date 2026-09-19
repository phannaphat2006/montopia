<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\FirebaseIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class FirebaseAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_config_exposes_only_web_configuration(): void
    {
        config([
            'firebase.enabled' => true,
            'firebase.project_id' => 'example-project',
            'firebase.web_api_key' => 'public-web-key',
            'firebase.credentials' => 'C:/private/service-account.json',
        ]);

        $this->getJson('/api/auth/firebase-config')->assertOk()->assertExactJson([
            'enabled' => true,
            'api_key' => 'public-web-key',
            'project_id' => 'example-project',
        ])->assertDontSee('service-account');
    }

    public function test_verified_firebase_identity_links_existing_user_and_starts_session(): void
    {
        config(['firebase.enabled' => true]);
        $user = User::factory()->create(['email' => 'admin@example.com', 'role' => 'admin', 'firebase_uid' => null]);
        $firebase = Mockery::mock(FirebaseIdentityService::class);
        $firebase->shouldReceive('verifyIdToken')->once()->with('valid-id-token')->andReturn([
            'uid' => 'firebase-uid-1',
            'email' => 'admin@example.com',
            'email_verified' => false,
        ]);
        $this->app->instance(FirebaseIdentityService::class, $firebase);

        $this->postJson('/api/auth/firebase', ['id_token' => 'valid-id-token'])
            ->assertOk()->assertJsonPath('user.role', 'admin');

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'firebase_uid' => 'firebase-uid-1']);
    }

    public function test_unknown_or_differently_linked_identity_cannot_enter(): void
    {
        config(['firebase.enabled' => true]);
        User::factory()->create(['email' => 'admin@example.com', 'firebase_uid' => 'original-uid']);
        $firebase = Mockery::mock(FirebaseIdentityService::class);
        $firebase->shouldReceive('verifyIdToken')->twice()->andReturn(
            ['uid' => 'unknown-uid', 'email' => 'missing@example.com', 'email_verified' => true],
            ['uid' => 'replacement-uid', 'email' => 'admin@example.com', 'email_verified' => true],
        );
        $this->app->instance(FirebaseIdentityService::class, $firebase);

        $this->postJson('/api/auth/firebase', ['id_token' => 'token-one'])->assertForbidden();
        $this->postJson('/api/auth/firebase', ['id_token' => 'token-two'])->assertForbidden();
        $this->assertGuest();
    }

    public function test_invalid_token_is_generic_and_never_echoed(): void
    {
        config(['firebase.enabled' => true]);
        $firebase = Mockery::mock(FirebaseIdentityService::class);
        $firebase->shouldReceive('verifyIdToken')->once()->andThrow(new RuntimeException('private credential path and token details'));
        $this->app->instance(FirebaseIdentityService::class, $firebase);

        $this->postJson('/api/auth/firebase', ['id_token' => 'secret-invalid-token'])
            ->assertUnprocessable()->assertJsonMissingExact(['message' => 'private credential path and token details'])
            ->assertDontSee('secret-invalid-token');
    }

    public function test_legacy_password_login_is_disabled_when_firebase_is_enabled(): void
    {
        config(['firebase.enabled' => true]);
        User::factory()->create(['email' => 'admin@example.com', 'password' => 'Valid-Password-2026']);

        $this->postJson('/api/auth/login', ['email' => 'admin@example.com', 'password' => 'Valid-Password-2026'])
            ->assertStatus(409);
        $this->assertGuest();
    }

    public function test_password_completion_requires_linked_authenticated_user(): void
    {
        config(['firebase.enabled' => true]);
        $linked = User::factory()->create(['firebase_uid' => 'firebase-uid', 'must_change_password' => true]);
        $unlinked = User::factory()->create(['firebase_uid' => null, 'must_change_password' => true]);

        $this->actingAs($unlinked)->postJson('/api/account/firebase-password-complete')->assertUnprocessable();
        $this->actingAs($linked)->postJson('/api/account/firebase-password-complete')->assertOk();
        $this->assertDatabaseHas('users', ['id' => $linked->id, 'must_change_password' => false]);
    }

    public function test_admin_create_update_and_delete_are_synchronized_with_firebase(): void
    {
        config(['firebase.enabled' => true]);
        $admin = User::factory()->create(['role' => 'admin']);
        $firebase = Mockery::mock(FirebaseIdentityService::class);
        $firebase->shouldReceive('enabled')->times(3)->andReturnTrue();
        $firebase->shouldReceive('createUser')->once()->andReturn('new-firebase-uid');
        $firebase->shouldReceive('updateUser')->once()->with('new-firebase-uid', Mockery::on(fn ($data) => $data['email'] === 'client-updated@example.com'));
        $firebase->shouldReceive('deleteUser')->once()->with('new-firebase-uid');
        $this->app->instance(FirebaseIdentityService::class, $firebase);

        $created = $this->actingAs($admin)->postJson('/api/admin/users', [
            'name' => 'Client One', 'email' => 'client@example.com', 'password' => 'Client-Password-2026', 'role' => 'client', 'phone' => null,
        ])->assertCreated()->json('data');
        $user = User::findOrFail($created['id']);
        $this->assertSame('new-firebase-uid', $user->firebase_uid);
        $this->assertFalse(Hash::check('Client-Password-2026', $user->password));

        $this->putJson('/api/admin/users/'.$user->id, [
            'name' => 'Client Updated', 'email' => 'client-updated@example.com', 'password' => null, 'role' => 'client', 'phone' => null,
        ])->assertOk();
        $this->deleteJson('/api/admin/users/'.$user->id)->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
