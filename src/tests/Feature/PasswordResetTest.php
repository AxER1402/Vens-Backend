<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_registered_user_receives_password_reset_link(): void
    {
        Notification::fake();

        $user = User::where('email', 'admin@vens.com')->first();

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_unknown_email_gets_generic_response_without_sending_mail(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'nadie@vens.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Si el correo está registrado, recibirá un enlace para restablecer su contraseña.',
            ]);

        Notification::assertNothingSent();
    }

    public function test_inactive_user_does_not_receive_password_reset_link(): void
    {
        Notification::fake();

        $user = User::where('email', 'enfermera@vens.com')->first();
        $user->update(['activo' => false]);

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        Notification::assertNothingSent();
    }

    public function test_forgot_password_requires_valid_email(): void
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'no-es-un-correo',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::where('email', 'medico@vens.com')->first();
        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'nuevaClave123',
            'password_confirmation' => 'nuevaClave123',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertTrue(Hash::check('nuevaClave123', $user->fresh()->password));
    }

    public function test_user_can_login_with_new_password_and_not_the_old_one(): void
    {
        $user = User::where('email', 'medico@vens.com')->first();
        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'nuevaClave123',
            'password_confirmation' => 'nuevaClave123',
        ])->assertStatus(200);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ])->assertStatus(401);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'nuevaClave123',
        ])->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_password_reset_fails_with_invalid_token(): void
    {
        $user = User::where('email', 'medico@vens.com')->first();

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'token-invalido',
            'email' => $user->email,
            'password' => 'nuevaClave123',
            'password_confirmation' => 'nuevaClave123',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'El token de restablecimiento es inválido o ha expirado.',
            ]);

        $this->assertTrue(Hash::check('Password123!', $user->fresh()->password));
    }

    public function test_reset_token_cannot_be_reused(): void
    {
        $user = User::where('email', 'medico@vens.com')->first();
        $token = Password::createToken($user);

        $payload = [
            'token' => $token,
            'email' => $user->email,
            'password' => 'nuevaClave123',
            'password_confirmation' => 'nuevaClave123',
        ];

        $this->postJson('/api/v1/auth/reset-password', $payload)->assertStatus(200);
        $this->postJson('/api/v1/auth/reset-password', $payload)->assertStatus(422);
    }

    public function test_reset_password_requires_matching_confirmation(): void
    {
        $user = User::where('email', 'medico@vens.com')->first();

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => Password::createToken($user),
            'email' => $user->email,
            'password' => 'nuevaClave123',
            'password_confirmation' => 'otraClave123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_existing_sanctum_tokens_are_revoked_after_reset(): void
    {
        $user = User::where('email', 'medico@vens.com')->first();
        $accessToken = $user->createToken('auth_token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$accessToken)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => Password::createToken($user),
            'email' => $user->email,
            'password' => 'nuevaClave123',
            'password_confirmation' => 'nuevaClave123',
        ])->assertStatus(200);

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // El guard conserva en memoria el usuario resuelto en la petición
        // anterior; se limpia para que el token se vuelva a validar contra la
        // base de datos, tal como ocurriría en una petición HTTP real.
        Auth::forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$accessToken)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }
}
