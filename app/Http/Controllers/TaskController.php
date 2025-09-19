<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class TaskController extends Controller
{
    /**
     *
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */

    public function store(Request $request)
    {
        // Получаем данные из JSON
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'fragments' => 'nullable',
            'file' => 'nullable|string',
        ]);

        $fragments = $data['fragments'] ?? [];
        if (!is_array($fragments)) {
            $fragments = [];
        }

        $task = Task::create([
            'title' => $data['title'],
            'subtitle' => $data['subtitle'] ?? null,
            'content' => json_encode($fragments),
            'file_path' => $data['file'] ?? null,
        ]);

        $task->categories()->attach($data['category_id']);

        return response()->json($task->load('categories'));
    }

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
