<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Traits\ConsumeMicroserviceService;

class ClassController extends Controller
{
    use ConsumeMicroserviceService;
    private $baseUri, $secret, $reqUrl = "class";

    public function __construct()
    {
        $this->baseUri = config('services.class.base_uri');
        $this->secret = config('services.class.secret');
    }

    
    public function index() {
        return $this->performRequest('GET', "{$this->reqUrl}/all");
    }

    public function show(Request $request) {
        return $this->performRequest('GET', "{$this->reqUrl}", $request->all());
    }
    
    public function store(Request $request) {
        return $this->performRequest('POST', "{$this->reqUrl}", $request->all());
    }
    
    public function update(Request $request) {
        return $this->performRequest('PATCH', "{$this->reqUrl}", $request->all());
    }

    public function destroy(Request $request) {
        return $this->performRequest('DELETE', "{$this->reqUrl}", $request->all());
    }
}
