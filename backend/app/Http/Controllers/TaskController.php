<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use App\Services\TaskService;
use Exception;

class TaskController extends Controller
{
    protected $taskService;

    public function __construct(TaskService $taskService)
    {
        $this->taskService = $taskService;
    }

    public function index()
    {
        $tasks = Task::with(['creator', 'assignments.user'])->orderBy('id', 'desc')->get();
        return response()->json($tasks);
    }

    public function store(Request $request)
    {
        if (!in_array($request->user()->role, ['Admin', 'Manager'])) {
            return response()->json(['message' => 'Unauthorized. Only Admins and Managers can create tasks.'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'required|string|in:Low,Medium,High',
            'due_date' => 'required|date',
            'status' => 'nullable|string|in:Todo,In Progress,Done,Completed',
            'rules' => 'nullable|array',
            'rules.department' => 'nullable|string',
            'rules.min_experience' => 'nullable|integer|min:0',
            'rules.max_active_tasks' => 'nullable|integer|min:1',
            'rules.location' => 'nullable|string'
        ]);

        try {
            $task = $this->taskService->createTask($validated, $request->user()->id);
            return response()->json($task, 201);
        } catch (Exception $e) {
            return response()->json(['message' => 'Failed to create task.', 'error' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $task = Task::with(['creator', 'assignments.user'])->findOrFail($id);
            return response()->json($task);
        } catch (Exception $e) {
            return response()->json(['message' => 'Task not found.'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        if (!in_array($request->user()->role, ['Admin', 'Manager'])) {
            return response()->json(['message' => 'Unauthorized. Only Admins and Managers can modify task details.'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'priority' => 'sometimes|required|string|in:Low,Medium,High',
            'due_date' => 'sometimes|required|date',
            'status' => 'sometimes|required|string|in:Todo,In Progress,Done,Completed',
            'rules' => 'nullable|array'
        ]);

        try {
            $task = $this->taskService->updateTask($id, $validated);
            return response()->json($task);
        } catch (Exception $e) {
            return response()->json(['message' => 'Failed to update task.', 'error' => $e->getMessage()], 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:Todo,In Progress,Done,Completed'
        ]);

        try {
            $task = $this->taskService->updateStatus($id, $validated['status']);
            return response()->json($task);
        } catch (Exception $e) {
            return response()->json(['message' => 'Failed to update task status.', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        if ($request->user()->role !== 'Admin') {
            return response()->json(['message' => 'Unauthorized. Only Admins can delete tasks.'], 403);
        }

        try {
            $this->taskService->deleteTask($id);
            return response()->json(null, 204);
        } catch (Exception $e) {
            return response()->json(['message' => 'Failed to delete task.', 'error' => $e->getMessage()], 500);
        }
    }
}


