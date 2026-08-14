<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Jobs\EvaluateTaskEligibility;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TaskService
{
    public function createTask(array $data, int $userId): Task
    {
        $data['status'] = $data['status'] ?? 'Todo';
        $data['created_by'] = $userId;

        $task = Task::create($data);

        // Dispatch background job for rule-based assignment
        EvaluateTaskEligibility::dispatch($task->id);

        return $task->load('assignments.user');
    }

    public function updateTask(int $taskId, array $data): Task
    {
        $task = Task::findOrFail($taskId);
        $oldStatus = $task->status;

        $task->update($data);
        
        // Re-evaluate eligibility if rules or status changed
        if (isset($data['rules']) || (isset($data['status']) && $data['status'] !== $oldStatus)) {
            EvaluateTaskEligibility::dispatch($task->id);
        }

        return $task->load('assignments.user');
    }

    public function updateStatus(int $taskId, string $status): Task
    {
        $task = Task::findOrFail($taskId);
        $task->status = $status;
        $task->save();

        EvaluateTaskEligibility::dispatch($task->id);

        return $task->load('assignments.user');
    }

    public function deleteTask(int $taskId): void
    {
        DB::transaction(function () use ($taskId) {
            $task = Task::findOrFail($taskId);
            
            $existingAssignment = TaskAssignment::where('task_id', $taskId)->first();
            if ($existingAssignment) {
                $userId = $existingAssignment->user_id;
                User::where('id', $userId)->where('active_tasks_count', '>', 0)->decrement('active_tasks_count');
                Cache::forget("user_{$userId}_tasks");
            }

            $task->delete();
        });
    }
}

