<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TaskController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('main');
})->name('main');

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/logout', [AuthController::class, 'logout'])->name('logout');
Route::post('/tasks/{task}/delete', [TaskController::class, 'destroy'])->middleware('auth');
Route::post('/tasks', [TaskController::class, 'store'])->middleware('auth');
Route::post('/upload-file', function (Illuminate\Http\Request $request) {
    $request->validate(['file' => 'required|file|max:10240']);
    $path = $request->file('file')->store('task_files', 'public');
    return response()->json(['url' => '/storage/' . $path]);
})->middleware('auth');
