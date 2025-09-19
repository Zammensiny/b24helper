<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TaskController;
use App\Models\Task;
use App\Models\Category;

Route::get('/tasks', [TaskController::class, 'getTasks']);
Route::get('/tasks/{id}', function ($id) {
    return Task::with('categories')->findOrFail($id);
});
Route::get('/categories', function () {
    return Category::select('id', 'value', 'color')->get();
});
Route::post('/tasks/{task}/delete', [TaskController::class, 'destroy'])->middleware('auth');
