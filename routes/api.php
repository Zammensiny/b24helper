<?php

use App\Http\Controllers\TaskController;
use App\Models\Category;
use App\Models\Task;
use Illuminate\Support\Facades\Route;

Route::get('/tasks', [TaskController::class, 'getTasks']);

Route::get('/tasks/{id}', function ($id) {
    sleep(1);
    return Task::with('categories')->findOrFail($id);
});
