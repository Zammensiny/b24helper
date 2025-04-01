<?php

use App\Http\Controllers\TaskController;
use App\Models\Category;
use Illuminate\Support\Facades\Route;

Route::get('/tasks', [TaskController::class, 'getTasks']);
