<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect('/');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('name', $request->login)->first();

        if ($user && Hash::check($request->password, $user->password) && $user->is_admin) {
            Auth::login($user);
            return redirect()->route('main');
        }

        return back()->withErrors(['login' => 'Неверный логин или пароль']);
    }

    public function logout()
    {
        Auth::logout();
        return redirect()->route('main');
    }
}
