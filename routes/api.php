<?php
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/tasks', function (Request $request) {
    $query = $request->input('query');

    // Если запрос пустой, просто возвращаем все задачи
    if ($query) {
        $tasks = Task::where('title', 'like', '%' . $query . '%')
            ->orWhere('subtitle', 'like', '%' . $query . '%')
            ->get();
    } else {
        $tasks = Task::all();
    }

    return response()->json($tasks);
});

