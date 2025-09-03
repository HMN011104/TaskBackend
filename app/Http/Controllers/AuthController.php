<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request) {
        $request->validate([
            'name'=>'required',
            'email'=>'required|email|unique:users',
            'password'=>'required|min:6'
        ]);

        $user = User::create([
            'name'=>$request->name,
            'email'=>$request->email,
            'password'=>Hash::make($request->password)
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json(['token'=>$token,'user'=>$user]);
    }

    public function login(Request $request) {
        $user = User::where('email',$request->email)->first();

        if(!$user || !Hash::check($request->password,$user->password)){
            return response()->json(['error'=>'Invalid credentials'],401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json(['token'=>$token,'user'=>$user]);
    }

    public function me(Request $request) {
        return response()->json($request->user());
    }

    public function logout(Request $request) {
        $request->user()->tokens()->delete();
        return response()->json(['message'=>'Logged out']);
    }
}

