<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_requires_authentication(): void
    {
        $response = $this->get('/register');

        $response->assertRedirect('/login');
    }

    public function test_registration_screen_can_be_rendered_by_staff(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/register');

        $response->assertStatus(200);
    }

    public function test_staff_can_register_a_new_user_without_losing_their_own_session(): void
    {
        $staff = User::factory()->create();

        $response = $this->actingAs($staff)->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);

        // La nueva cuenta se crea, pero la sesión sigue siendo la del staff
        // que la dio de alta (ver RegisteredUserController::store).
        $this->assertAuthenticatedAs($staff);
        $response->assertRedirect(route('documents.index', absolute: false));
    }
}
