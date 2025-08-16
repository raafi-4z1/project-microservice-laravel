<?php

namespace App\Http\Controllers\Oauth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Traits\ApiResponser;
use Illuminate\Http\Response;
use Auth;

class AuthController extends Controller
{
    use ApiResponser;
    function register(Request $request) {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required',
                'email' => 'required|email',
                'password' => 'required|min:6',
                'confirm_password' => 'required|same:password'
            ]);

            if ($validator->fails()) {
                return $this->response(
                    $validator->messages()->first(),
                    Response::HTTP_BAD_REQUEST,
                    $validator->errors()->all()
                );
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($request->password)
            ]);

            $data = [
                // 'token' => $user->createToken('My Token')->accessToken,
                'user' => $user->name,
                'email' => $user->email
            ];

            return $this->response("User registered.", Response::HTTP_CREATED, $data);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    function login(Request $request) {
        try {
            if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
                $user = Auth::user();

                $data = [
                    'token' => $user->createToken("My Token")->accessToken,
                    'user' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role
                ];

                return $this->response("Access granted.", Response::HTTP_OK, $data);
            }

            return $this->response("Invalid user credentials.", Response::HTTP_BAD_REQUEST);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->token()->revoke();
        return $this->response("Logged out.", Response::HTTP_OK);
    }
}
