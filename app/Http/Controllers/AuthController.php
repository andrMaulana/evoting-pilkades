<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    // Nampilin halaman form login
    public function index()
    {
        return view('login');
    }

    // Proses ngecek email & password
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        // Kalau email dan password cocok dengan yang ada di database
        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            
            // Arahkan ke halaman dashboard admin
            return redirect()->intended('/admin/dashboard');
        }

        // Kalau gagal/salah password, kembalikan ke halaman login bawa pesan error
        return back()->withErrors([
            'email' => 'Email atau password salah, Ndri!',
        ])->onlyInput('email');
    }

    // Proses keluar/logout
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}