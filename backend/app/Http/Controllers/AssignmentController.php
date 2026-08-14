<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AssignmentController extends Controller
{
    public function getEligibleUsers($id)
    {
        $task = Task::findOrFail($id);
        $users = $task->assignments()->with('user')->get()->pluck('user');
        return response()->json($users);
    }

    public function getMyEligibleTasks(Request $request)
    {
        $userId = $request->user()->id;
        
        // Cache the eligible tasks for 5 minutes (300 seconds)
        $tasks = Cache::remember("user_{$userId}_tasks", 300, function () use ($userId) {
            $user = User::find($userId);
            if (!$user) return [];
            
            return $user->assignments()
                        ->with(['task.creator', 'task.assignments.user'])
                        ->get()
                        ->pluck('task');
        });

        return response()->json($tasks);
    }

    public function recomputeEligibility(Request $request)
    {
        if (!in_array($request->user()->role, ['Admin', 'Manager'])) {
            return response()->json(['message' => 'Unauthorized. Only Admins and Managers can trigger recomputation.'], 403);
        }

        $validated = $request->validate([
            'task_id' => 'required|exists:tasks,id'
        ]);

        \App\Jobs\EvaluateTaskEligibility::dispatch($validated['task_id']);

        return response()->json(['message' => 'Recomputation queued successfully']);
    }
}

