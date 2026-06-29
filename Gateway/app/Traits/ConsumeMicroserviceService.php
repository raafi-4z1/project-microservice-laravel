<?php

namespace App\Traits;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

trait ConsumeMicroserviceService
{
    /**
     * Send request to any service
     * @param $method
     * @param $requestUrl
     * @param $formParams
     * @return $contentType
     */
    public function performRequest($method, $requestUrl, $formParams = [], array $extraHeaders = [])
    {
        $method = strtoupper($method);
        $timestamp = time();

        $hasFile = collect($formParams)->contains(fn($v) =>
            $v instanceof \Illuminate\Http\UploadedFile || is_resource($v)
        );

        // Body yang akan dikirim menentukan HMAC yang ditandatangani:
        // - POST/PATCH JSON  : sign json_encode (cocok dengan $request->getContent() di service)
        // - POST multipart   : sign "" (binary boundary tidak bisa di-hash deterministik)
        // - GET/DELETE       : sign http_build_query (params via query string)
        if (in_array($method, ['POST', 'PATCH'])) {
            if ($hasFile) {
                $hmacBody = '';
                $options['multipart'] = $this->buildMultipart($formParams);
            } else {
                $hmacBody = json_encode($formParams);
                $options['json'] = $formParams;
            }
        } else {
            // Sign query params agar GET/DELETE tidak bisa di-tamper di transit
            $hmacBody = http_build_query($formParams);
            $options['query'] = $formParams;
        }

        $signature = hash_hmac('sha256', $timestamp . $hmacBody, $this->secret);

        $options['headers'] = array_merge([
            'X-Timestamp'     => $timestamp,
            'X-Signature'     => $signature,
            'X-Forwarded-For' => request()->ip(),
        ], $extraHeaders);
        $options['timeout']         = 30;
        $options['connect_timeout'] = 5;
        
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
                $path = $value->getRealPath() ?: $value->getPathname();
                $multipart[] = [
                    'name'     => $name,
                    'contents' => fopen($path, 'r'),
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
