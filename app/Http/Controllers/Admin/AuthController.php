<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Auth;

class AuthController extends Controller
{
    public function login(Request $request){
        $credentials = $request->only('email', 'password');
        if(auth()->guard('web')->attempt($credentials)){
            return redirect()->route('admin.dashboard');
        }
        return redirect()->back()->with('error', 'Invalid Credetials')->withInput();
    }

    public function logout(){
        auth()->guard('web')->logout();

        return redirect()->route('admin.auth.index');
    }
}
