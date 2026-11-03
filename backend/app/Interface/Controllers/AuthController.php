<?php
namespace App\Interface\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate(['username' => 'required|string', 'password' => 'required|string']);
        $user = \App\Models\User::where('username', $request->username)->first();
        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages(['username' => ['認証に失敗しました。']]);
        }
        $token = $user->createToken('auth-token')->plainTextToken;
        return response()->json(['token' => $token, 'user' => ['id' => $user->id, 'username' => $user->username, 'display_name' => $user->display_name]]);
    }

    public function logout(Request $request)
    {
        if ($request->user() && $request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }
        return response()->json(['message' => 'ログアウトしました。']);
    }

    public function user(Request $request)
    {
        return response()->json($request->user()->only(['id', 'username', 'display_name']));
    }
}
