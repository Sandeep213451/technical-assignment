<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Task;
use App\Models\TaskAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TaskManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_task()
    {
        $admin = User::factory()->create(['role' => 'Admin']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/tasks', [
            'title' => 'System Migration',
            'description' => 'Migrate database to MySQL 8',
            'priority' => 'High',
            'due_date' => '2026-12-31',
            'rules' => [
                'department' => 'IT',
                'min_experience' => 5
            ]
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('title', 'System Migration');

        $this->assertDatabaseHas('tasks', [
            'title' => 'System Migration',
            'priority' => 'High'
        ]);
    }

    public function test_regular_user_cannot_create_task()
    {
        $user = User::factory()->create(['role' => 'User']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/tasks', [
            'title' => 'Unauthorized Task',
            'description' => 'Should fail',
            'priority' => 'Low',
            'due_date' => '2026-12-31'
        ]);

        $response->assertStatus(403);
    }

    public function test_can_update_task_status()
    {
        $user = User::factory()->create(['role' => 'User']);
        $task = Task::factory()->create(['status' => 'Todo']);

        $response = $this->actingAs($user, 'sanctum')->patchJson("/api/tasks/{$task->id}/status", [
            'status' => 'Completed'
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'Completed');

        $this->assertEquals('Completed', $task->fresh()->status);
    }

    public function test_admin_can_delete_task()
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $task = Task::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/tasks/{$task->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }
}
