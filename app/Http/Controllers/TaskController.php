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

        if ($query) {
            $tasks = Task::where('title', 'like', '%' . $query . '%')
                ->orWhere('subtitle', 'like', '%' . $query . '%')
                ->latest()
                ->get();
        } else {
            $tasks = Task::latest()->take(15)->get();
        }

        return response()->json($tasks);
    }
}
