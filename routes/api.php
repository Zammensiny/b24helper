<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AiProxyController;
use App\Http\Controllers\TaskController;
use App\Models\Task;
use App\Models\Category;

Route::post('/ai', [AiProxyController::class, 'reply'])->middleware('throttle:10,1');

Route::get('/tasks', [TaskController::class, 'getTasks']);
Route::get('/tasks/{id}', function ($id) {
    return Task::with('categories')->findOrFail($id);
});
Route::get('/categories', function () {
    return Category::select('id', 'value', 'color')->get();
});
