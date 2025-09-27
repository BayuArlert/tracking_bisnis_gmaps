<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // register
    public function register(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:255|unique:users',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'username' => $request->username,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'Register berhasil',
            'user' => $user,
        ], 201);
    }

    // login
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('username', 'password'))) {
            return response()->json([
                'message' => 'Username atau password salah',
            ], 401);
        }

        $user = User::where('username', $request->username)->first();
        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'user' => $user,
        ]);
    }

    // logout
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Logout berhasil'
        ]);
    }
}
