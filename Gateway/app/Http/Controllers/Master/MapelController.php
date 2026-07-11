<?php

namespace App\Http\Controllers\Master;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Traits\ApiResponser;
use App\Traits\LogsAudit;
use App\Http\Controllers\Controller;
use App\Traits\ConsumeMicroserviceService;

class MapelController extends Controller
{
    use ConsumeMicroserviceService, ApiResponser, LogsAudit;
    private $baseUri, $secret, $reqUrl;

    public function __construct()
    {
        $this->reqUrl = config('gateway.mapel_prefix');
        $this->baseUri = config('services.mapel.base_uri');
        $this->secret = config('services.mapel.secret');
    }

    public function index(Request $request)
    {
        return $this->performRequest($request->method(), "{$this->reqUrl}/all", $request->only(['page', 'per_page', 'search']));
    }

    public function show(Request $request)
    {
        return $this->performRequest($request->method(), "{$this->reqUrl}", $request->only(['idPelajaran']));
    }

    public function store(Request $request)
    {
        try {
            $response = $this->performRequest($request->method(), "{$this->reqUrl}", $request->all());
            $decode = $this->decode($response);

            if (($decode['resCode'] ?? null) === Response::HTTP_CREATED) {
                $this->auditLog('created', 'mapel', $decode['data']['idPelajaran'] ?? null, [
                    'namaPelajaran' => $request->namaPelajaran,
                    'kode'          => $request->kode,
                ]);
            }

            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(Request $request)
    {
        try {
            $response = $this->performRequest($request->method(), "{$this->reqUrl}/update", $request->all());
            $decode = $this->decode($response);

            if (($decode['resCode'] ?? null) === Response::HTTP_ACCEPTED) {
                $this->auditLog('updated', 'mapel', $request->idPelajaran, array_filter([
                    'namaPelajaran' => $request->namaPelajaran,
                    'kode'          => $request->kode,
                ]));
            }

            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $response = $this->performRequest('DELETE', "{$this->reqUrl}/{$id}");
            $decode = $this->decode($response);

            if (($decode['resCode'] ?? null) === Response::HTTP_ACCEPTED) {
                $this->auditLog('deleted', 'mapel', $id, []);
            }

            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function decode($response): array
    {
        $raw = $response instanceof \Illuminate\Http\Response
            ? $response->getContent()
            : $response;
        return json_decode($raw, true) ?? [];
    }
}
