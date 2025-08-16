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
     * @param String $contentType
     * @return $contentType
     */
    public function performRequest($method, $requestUrl, $formParams = [], String $contentType = 'application/json')
    {
        $options = [
            'headers' => [
                'Authorization' => $this->secret,
                'Accept'        => 'application/json',
                'X-Forwarded-For' => request()->header('X-Forwarded-For'),
            ],
        ];
        $method = strtoupper($method);

        if ($method === 'POST') {
            $options['form_params'] = $formParams;
        } else {
            $options['query'] = $formParams;
        }
        
        $client = new Client(['base_uri'  =>  rtrim($this->baseUri, '/'),]);
        try {
            $response = $client->request($method, '/api/'.$requestUrl, $options);
        }
        catch (RequestException $e) {
            // Kalau ada response (4xx/5xx), ambil response dari exception
            if ($e->hasResponse()) {
                $response = $e->getResponse();
            } else {
                throw $e;
            }
        }
		
		// $response = json_decode($response->getBody()->getContents(), true);
        // return response($response, $response['resCode'] ?? Response::HTTP_INTERNAL_SERVER_ERROR);
        return response($response->getBody()->getContents(), $response->getStatusCode())->header('Content-Type', $contentType);
    }
}
