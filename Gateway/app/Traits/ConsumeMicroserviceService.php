<?php

namespace App\Traits;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Response;

trait ConsumeMicroserviceService
{
    /**
     * Send request to any service
     * @param $method
     * @param $requestUrl
     * @param $formParams
     * @return $contentType
     */
    public function performRequest($method, $requestUrl, $formParams = [])
    {
        $method = strtoupper($method);
        $options = [
            'headers' => [
                'Authorization' => $this->secret,
                'X-Forwarded-For' => request()->header('X-Forwarded-For'),
            ],
            'timeout'         => 600,
            'connect_timeout' => 30,
        ];

        $hasFile = collect($formParams)->contains(fn($v) =>
            $v instanceof \Illuminate\Http\UploadedFile || is_resource($v)
        );

        if ($method === 'POST') {
            if ($hasFile) {
                $options['multipart'] = $this->buildMultipart($formParams);
            } else {
                $options['form_params'] = $formParams;
            }
        } else {
            $options['query'] = $formParams;
        }
        
        $client = new Client(['base_uri'  =>  rtrim($this->baseUri, '/')]);
        try {
            $response = $client->request($method, '/api/'.$requestUrl, $options);
        }
        catch (RequestException $e) {
            // Kalau ada response (4xx/5xx), ambil response dari exception
            $response = $e->hasResponse() ? $e->getResponse() : throw $e;
        }
		
		// $response = json_decode($response->getBody()->getContents(), true);
        // return response($response, $response['resCode'] ?? Response::HTTP_INTERNAL_SERVER_ERROR);
        $serviceContentType = $response->getHeaderLine('Content-Type')
                              ?: 'application/octet-stream';

        return response(
            $response->getBody()->getContents(),
            $response->getStatusCode()
        )->header('Content-Type', $serviceContentType);
    }

    protected function buildMultipart(array $params): array
    {
        $multipart = [];

        foreach ($params as $name => $value) {
            if ($value instanceof \Illuminate\Http\UploadedFile) {
                $multipart[] = [
                    'name'     => $name,
                    'contents' => fopen($value->getRealPath(), 'r'),
                    'filename' => $value->getClientOriginalName(),
                    'headers'  => ['Content-Type' => $value->getMimeType()],
                ];
            } elseif (is_resource($value)) {
                $multipart[] = ['name' => $name, 'contents' => $value];
            } else {
                $multipart[] = ['name' => $name, 'contents' => (string) $value];
            }
        }

        return $multipart;
    }
}
