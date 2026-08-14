<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register()
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Sandeep Senapati',
            'email' => 'sandeep@example.com',
            'password' => 'secret123',
            'role' => 'User',
            'department' => 'IT',
            'years_of_experience' => 4,
            'location' => 'Bhubaneswar'
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure(['token', 'user']);

        $this->assertDatabaseHas('users', [
            'email' => 'sandeep@example.com',
            'department' => 'IT'
        ]);
    }

    public function test_user_can_login()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123')
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['token', 'user']);
    }

    public function test_user_can_update_profile_and_trigger_observer()
    {
        $user = User::factory()->create([
            'department' => 'HR',
            'years_of_experience' => 2,
            'location' => 'Cuttack'
        ]);

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/user/profile', [
            'department' => 'IT',
            'years_of_experience' => 6,
            'location' => 'Bhubaneswar'
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'department' => 'IT',
            'years_of_experience' => 6
        ]);
    }
}
