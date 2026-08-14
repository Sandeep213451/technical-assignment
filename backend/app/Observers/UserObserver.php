<?php

namespace App\Observers;

use App\Models\User;
use App\Models\Task;
use App\Jobs\EvaluateTaskEligibility;

class UserObserver
{
    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        // re-run task matching if profile changes
        // don't check active_tasks_count here or it will loop infinitely
        if ($user->wasChanged(['department', 'years_of_experience', 'location'])) {
            $tasks = Task::whereNotIn('status', ['Completed', 'Done'])->pluck('id');
            foreach ($tasks as $taskId) {
                EvaluateTaskEligibility::dispatch($taskId);
            }
        }
    }
}

