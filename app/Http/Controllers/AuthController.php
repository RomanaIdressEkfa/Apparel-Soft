<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, true)) {
            if (! $request->user()->is_approved) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()
                    ->withErrors(['email' => 'Your account is waiting for admin approval. You will be able to log in once an admin approves it.'])
                    ->onlyInput('email');
            }

            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        }

        return back()
            ->withErrors(['email' => 'Incorrect email or password.'])
            ->onlyInput('email');
    }

    public function showRegister()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.register');
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'name.required' => 'Please enter your name.',
            'email.required' => 'Please enter your email.',
            'email.email' => 'Please enter a valid email.',
            'email.unique' => 'An account with this email already exists. Please log in.',
            'password.required' => 'Please enter a password.',
            'password.min' => 'Password must be at least 6 characters.',
            'password.confirmed' => 'The passwords do not match.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput($request->except('password', 'password_confirmation'));
        }

        User::create([
            'name' => $request->string('name'),
            'email' => $request->string('email'),
            'password' => Hash::make($request->string('password')),
            'is_approved' => false,
            'role' => 'member',
        ]);

        return redirect()->route('login')->with(
            'status',
            'Account created. An admin has to approve it before you can log in — please check back shortly.'
        );
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
