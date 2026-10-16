<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('mfa.challenge', absolute: false));
        $this->assertSame(User::where('email', 'test@example.com')->firstOrFail()->id, session('auth.mfa.user_id'));
        $this->assertDatabaseHas('users', ['email' => 'test@example.com', 'mfa_enabled' => true]);
        $this->get('/dashboard')->assertRedirect('/login');
    }
}
