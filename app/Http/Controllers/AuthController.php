<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // 🔹 Register
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|string|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        try {
            User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            return redirect()->route('login')->with('toast', [
                'type' => 'success',
                'message' => 'Registrasi berhasil, silakan login!',
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with('toast', [
                'type' => 'error',
                'message' => 'Registrasi gagal, coba lagi.',
            ]);
        }
    }

    // 🔹 Login
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            return redirect()->intended('/')->with('toast', [
                'type' => 'success',
                'message' => 'Login berhasil, selamat datang!',
            ]);
        }

        return redirect()->back()->with('toast', [
            'type' => 'error',
            'message' => 'Email atau password salah!',
        ]);
    }

    // 🔹 Logout
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with('toast', [
            'type' => 'success',
            'message' => 'Logout berhasil!',
        ]);
    }
}
