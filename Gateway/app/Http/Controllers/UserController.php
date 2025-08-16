<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UserController extends Controller
{
    use ApiResponser;
    
    public function index(Request $request)
    {
        return $this->response('Data user.', Response::HTTP_OK, $request->user());
    }
}
