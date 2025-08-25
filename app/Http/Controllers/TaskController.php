<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    /**
     *
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTasks(Request $request)
    {
        $query = $request->input('query');

        $tasksQuery = Task::query()->with('categories');

        if ($query) {
            $tasksQuery->where('title', 'like', '%' . $query . '%')
                ->orWhere('subtitle', 'like', '%' . $query . '%');
        }

        $tasks = $tasksQuery->latest()->take(20)->get();

        return response()->json($tasks);
    }

    public function destroy(Task $task)
    {
        if (!auth()->user() || !auth()->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $task->delete();

        return response()->json(['message' => 'Task deleted successfully']);
    }
}
