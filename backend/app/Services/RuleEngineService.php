<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use App\Models\TaskAssignment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RuleEngineService
{
    /**
     * Evaluates users for a given task and assigns it based on business rules.
     * Uses pessimistic locking to prevent race conditions during assignment.
     *
     * @param int $taskId
     * @return void
     */
    public function assignTask(int $taskId): void
    {
        DB::transaction(function () use ($taskId) {
            $task = Task::where('id', $taskId)->lockForUpdate()->first();
            if (!$task) {
                return;
            }

            // skip if task is already done
            if (in_array($task->status, ['Completed', 'Done'])) {
                $this->releaseAssignment($taskId);
                return;
            }

            $eligibleUser = $this->findOptimalUser($task->rules ?? []);
            $existingAssignment = TaskAssignment::where('task_id', $taskId)->first();

            if ($eligibleUser) {
                if ($existingAssignment) {
                    if ($existingAssignment->user_id === $eligibleUser->id) {
                        // Already assigned to the optimal user
                        return;
                    }
                    // Re-assignment required: release old user workload
                    $this->releaseAssignment($taskId, $existingAssignment);
                }

                // Create new assignment
                TaskAssignment::create([
                    'task_id' => $task->id,
                    'user_id' => $eligibleUser->id,
                    'assigned_at' => now()
                ]);

                $eligibleUser->increment('active_tasks_count');
                Cache::forget("user_{$eligibleUser->id}_tasks");
                
                Log::info("Task [{$task->id}] automatically assigned to User [{$eligibleUser->id}]");
            } else {
                if ($existingAssignment) {
                    // rules changed and no one matches anymore, so remove old assignment
                    $this->releaseAssignment($taskId, $existingAssignment);
                }

                Log::info("Task [{$task->id}] could not be assigned. No eligible users found.");
            }
        });
    }

    /**
     * Finds the optimal user based on hard rules and tie-breakers.
     * 
     * @param array $rules
     * @return User|null
     */
    private function findOptimalUser(array $rules): ?User
    {
        $query = User::query();

        if (!empty($rules['department'])) {
            $query->where('department', $rules['department']);
        }
        
        if (isset($rules['min_experience']) && $rules['min_experience'] > 0) {
            $query->where('years_of_experience', '>=', $rules['min_experience']);
        }
        
        if (isset($rules['max_active_tasks']) && $rules['max_active_tasks'] !== null) {
            $query->where('active_tasks_count', '<', $rules['max_active_tasks']);
        }

        if (!empty($rules['location'])) {
            $query->where('location', $rules['location']);
        }

        if (!empty($rules['role'])) {
            $query->where('role', $rules['role']);
        }

        // sort by lowest tasks first, then by experience if tied
        return $query->orderBy('active_tasks_count', 'asc')
                     ->orderBy('years_of_experience', 'desc')
                     ->orderBy('id', 'asc')
                     ->lockForUpdate()
                     ->first();
    }

    /**
     * Releases an existing assignment, decrementing the user's workload.
     *
     * @param int $taskId
     * @param TaskAssignment|null $assignment (Optional optimization)
     */
    private function releaseAssignment(int $taskId, ?TaskAssignment $assignment = null): void
    {
        $assignment = $assignment ?? TaskAssignment::where('task_id', $taskId)->first();
        if ($assignment) {
            $oldUserId = $assignment->user_id;
            $assignment->delete();
            User::where('id', $oldUserId)->where('active_tasks_count', '>', 0)->decrement('active_tasks_count');
            Cache::forget("user_{$oldUserId}_tasks");
        }
    }
}
