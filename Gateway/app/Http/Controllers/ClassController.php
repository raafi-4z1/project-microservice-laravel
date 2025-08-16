<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Traits\ConsumeMicroserviceService;

class ClassController extends Controller
{
    use ConsumeMicroserviceService;
    private $baseUri, $secret, $reqUrl;

    public function __construct()
    {
        $this->reqUrl = config('gateway.class_prefix');
        $this->baseUri = config('services.class.base_uri');
        $this->secret = config('services.class.secret');
    }
    
    public function index(Request $request) {
        return $this->performRequest($request->method(), "{$this->reqUrl}/all");
    }

    public function show(Request $request) {
        return $this->performRequest($request->method(), "{$this->reqUrl}", $request->all());
    }
    
    public function store(Request $request) {
        return $this->performRequest($request->method(), "{$this->reqUrl}", $request->all());
    }
    
    public function update(Request $request) {
        return $this->performRequest($request->method(), "{$this->reqUrl}", $request->all());
    }

    public function destroy(Request $request) {
        return $this->performRequest($request->method(), "{$this->reqUrl}", $request->all());
    }
}
