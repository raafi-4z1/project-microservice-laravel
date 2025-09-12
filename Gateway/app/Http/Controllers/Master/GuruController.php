<?php

namespace App\Http\Controllers\Master;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Traits\ApiResponser;
use App\Services\UserService;
use App\Http\Controllers\Controller;
use App\Traits\ConsumeMicroserviceService;

class GuruController extends Controller
{
    use ConsumeMicroserviceService, ApiResponser;
    private $userService, $baseUri, $secret, $reqUrl;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
        $this->reqUrl = config('gateway.guru_prefix');
        $this->baseUri = config('services.guru.base_uri');
        $this->secret = config('services.guru.secret');
    }
    
    public function index(Request $request) {
        return $this->performRequest($request->method(), "{$this->reqUrl}/all", $request->all());
    }

    public function show(Request $request) {
        return $this->performRequest($request->method(), "{$this->reqUrl}", $request->all());
    }
    
    public function store(Request $request) {
        try {
            $response = $this->performRequest($request->method(), "{$this->reqUrl}", $request->all());
            if (($this->decode($response)['resCode'] ?? null) === Response::HTTP_CREATED) {
                $this->userService->create($request->namaLengkap, $request->email, "Guru");
            }

            return $response;
        } catch (Exception $e) {
            return $this->response(
                $e->getMessage(), 
                Response::HTTP_INTERNAL_SERVER_ERROR, 
                $e
            );
        }
    }
    
    public function update(Request $request) {
        try {
            $response = $this->performRequest($request->method(), "{$this->reqUrl}/update", $request->all());
            $decode = $this->decode($response);
            if (($decode['resCode'] ?? null) === Response::HTTP_ACCEPTED && $request->filled('namaLengkap')) {
                $this->userService->update($decode['data']['email'], $request->namaLengkap);
            }
            
            return $response;
        } catch (Exception $e) {
            return $this->response(
                $e->getMessage(), 
                Response::HTTP_INTERNAL_SERVER_ERROR, 
                $e
            );
        }
    }

    public function destroy(Request $request) {
        try {
            $response = $this->performRequest($request->method(), "{$this->reqUrl}", $request->all());
            $decode = $this->decode($response);
            if (($decode['resCode'] ?? null) === Response::HTTP_ACCEPTED) {
                $this->userService->delete($decode['data']['email']);
            }
            
            return $response;
        } catch (Exception $e) {
            return $this->response(
                $e->getMessage(), 
                Response::HTTP_INTERNAL_SERVER_ERROR, 
                $e
            );
        }
    }

    private function decode($response) {
        // If it's an HTTP Response object, grab its content; otherwise assume it's already a string
        $rawBody = $response instanceof \Illuminate\Http\Response
            ? $response->getContent()
            : $response;

        // Decode JSON into associative array
        return json_decode($rawBody, true);
    }
}
