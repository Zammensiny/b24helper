<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;

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
}
