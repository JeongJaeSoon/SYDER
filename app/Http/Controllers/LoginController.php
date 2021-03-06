<?php

namespace App\Http\Controllers;

use App\Model\Authenticator;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

class LoginController extends Controller
{
    /**
     * @var Authenticator
     */
    private $authenticator;

    public function __construct(Authenticator $authenticator)
    {
        $this->authenticator = $authenticator;
    }

    /**
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     * @throws AuthenticationException
     */

    // API : [POST] /api/login
    public function login(Request $request)
    {
        // [CHECK VALIDATION]
        $validator = Validator::make($request->all(), [
            'account' => 'required|string',
            'password' => 'required|string|min:8',
            'provider' => 'required|string',
        ]);

        if ($validator->fails()) {
            return \response()->json([
                'message' => $validator->errors(),
            ], 422);
        }

        if ($request->provider === 'users') {
            $validator = Validator::make($request->all(), [
                'fcm_token' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors()
                ], 422);
            }
        }

        $credentials = array_values($request->only('account', 'password', 'provider'));

        if (!$user = $this->authenticator->attempt(...$credentials)) {
//            throw new AuthenticationException();
            return response()->json([
                'message' => 'Incorrect Account or Password'
            ], 401);
        }

        $token = $user->createToken(ucfirst($credentials[2]) . ' Token')->accessToken;

        if ($user->fcm_token != $request->fcm_token) {
            $user->update([
                'fcm_token' => $request->fcm_token,
            ]);
        }

        return response()->json([
            'message' => ucfirst($credentials[2]) . ' Login Success',
            'user' => $user,
            'access_token' => $token,
        ], 200);
    }

    // API : [POST] /api/logout
    public function logout(Request $request)
    {
        if (!($request->guard === 'admin' || $request->guard === 'user')) {
            return response()->json([
                'message' => 'This page is only accessible to admin or user',
            ], 403);
        }

        $request->user($request->guard)->token()->revoke();
        Auth::guard()->logout();
        Session::flush();

        return response()->json([
            'message' => ucfirst($request->guard) . ' Logout Success',
        ], 200);
    }

    // API : [GET] /api/authCheck
    public function authCheck(Request $request)
    {
        if (!($request->guard === 'admin' || $request->guard === 'user')) {
            return response()->json([
                'message' => 'This page is only accessible to admin or user',
            ], 403);
        }

        $user = $request->user($request->guard);

        return response()->json([
            'classification' => $request->guard,
            'id' => $user->id,
            'name' => $user->name,
        ]);
    }
}
