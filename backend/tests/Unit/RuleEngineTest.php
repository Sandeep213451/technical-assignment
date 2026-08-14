<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Jobs\EvaluateTaskEligibility;
use App\Services\RuleEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RuleEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigns_task_to_user_matching_all_rules()
    {
        $user1 = User::factory()->create([
            'name' => 'Alice',
            'department' => 'IT',
            'years_of_experience' => 5,
            'active_tasks_count' => 0,
            'location' => 'Bhubaneswar'
        ]);

        $user2 = User::factory()->create([
            'name' => 'Bob',
            'department' => 'HR',
            'years_of_experience' => 10,
            'active_tasks_count' => 0,
            'location' => 'Cuttack'
        ]);

        $task = Task::factory()->create([
            'title' => 'Fix Server Bug',
            'rules' => [
                'department' => 'IT',
                'min_experience' => 3,
                'max_active_tasks' => 5,
                'location' => 'Bhubaneswar'
            ]
        ]);

        app(RuleEngineService::class)->assignTask($task->id);

        $assignment = TaskAssignment::where('task_id', $task->id)->first();

        $this->assertNotNull($assignment);
        $this->assertEquals($user1->id, $assignment->user_id);
        $this->assertEquals(1, $user1->fresh()->active_tasks_count);
        $this->assertEquals(0, $user2->fresh()->active_tasks_count);
    }

    public function test_tie_breaker_prefers_user_with_lowest_active_tasks()
    {
        // User A: 5 yrs exp, 1 active task
        $userA = User::factory()->create([
            'department' => 'IT',
            'years_of_experience' => 5,
            'active_tasks_count' => 1
        ]);

        // User B: 3 yrs exp, 0 active tasks (Lower workload should win)
        $userB = User::factory()->create([
            'department' => 'IT',
            'years_of_experience' => 3,
            'active_tasks_count' => 0
        ]);

        $task = Task::factory()->create([
            'rules' => [
                'department' => 'IT',
                'min_experience' => 2
            ]
        ]);

        app(RuleEngineService::class)->assignTask($task->id);

        $assignment = TaskAssignment::where('task_id', $task->id)->first();
        $this->assertEquals($userB->id, $assignment->user_id);
    }

    public function test_tie_breaker_prefers_highest_experience_when_active_tasks_are_equal()
    {
        // User A: 8 yrs exp, 0 active tasks
        $userA = User::factory()->create([
            'name' => 'Senior Dev',
            'department' => 'IT',
            'years_of_experience' => 8,
            'active_tasks_count' => 0
        ]);

        // User B: 3 yrs exp, 0 active tasks
        $userB = User::factory()->create([
            'name' => 'Junior Dev',
            'department' => 'IT',
            'years_of_experience' => 3,
            'active_tasks_count' => 0
        ]);

        $task = Task::factory()->create([
            'rules' => [
                'department' => 'IT',
                'min_experience' => 2
            ]
        ]);

        app(RuleEngineService::class)->assignTask($task->id);

        $assignment = TaskAssignment::where('task_id', $task->id)->first();
        $this->assertEquals($userA->id, $assignment->user_id);
    }

    public function test_handles_no_eligible_user_gracefully()
    {
        $user = User::factory()->create([
            'department' => 'HR',
            'years_of_experience' => 1,
            'active_tasks_count' => 0
        ]);

        $task = Task::factory()->create([
            'rules' => [
                'department' => 'IT', // HR user does not match IT
                'min_experience' => 5
            ]
        ]);

        app(RuleEngineService::class)->assignTask($task->id);

        $assignment = TaskAssignment::where('task_id', $task->id)->first();
        $this->assertNull($assignment);
    }

    public function test_task_reassignment_updates_active_task_counts_correctly()
    {
        $user1 = User::factory()->create([
            'department' => 'IT',
            'years_of_experience' => 10,
            'active_tasks_count' => 0
        ]);

        $task = Task::factory()->create([
            'rules' => [
                'department' => 'IT',
                'min_experience' => 5
            ]
        ]);

        // Initial assignment
        app(RuleEngineService::class)->assignTask($task->id);
        $this->assertEquals(1, $user1->fresh()->active_tasks_count);

        // A new more eligible user joins (0 active tasks vs user1's 1 active task)
        $user2 = User::factory()->create([
            'department' => 'IT',
            'years_of_experience' => 12,
            'active_tasks_count' => 0
        ]);

        // Re-evaluate eligibility
        app(RuleEngineService::class)->assignTask($task->id);

        $assignment = TaskAssignment::where('task_id', $task->id)->first();
        $this->assertEquals($user2->id, $assignment->user_id);
        $this->assertEquals(0, $user1->fresh()->active_tasks_count); // Decremented!
        $this->assertEquals(1, $user2->fresh()->active_tasks_count); // Incremented!
    }
}
