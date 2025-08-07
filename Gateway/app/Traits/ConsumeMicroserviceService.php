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
     * @param array $formParams
     * @return string
     */
    public function performRequest($method, $requestUrl, $formParams = [])
    {
        $options = [
            'headers' => [
                'Authorization' => $this->secret,
                'Accept'        => 'application/json',
            ],
        ];
        $method = strtoupper($method);

        if ($method === 'POST') {
            $options['form_params'] = $formParams;
        } else {
            $options['query'] = $formParams;
        }
        
        $client = new Client(['base_uri'  =>  $this->baseUri,]);
        try {
            $response = $client->request(strtoupper($method), '/api/'.$requestUrl, $options);
        }
        catch (RequestException $e) {
            // Kalau ada response (4xx/5xx), ambil response dari exception
            if ($e->hasResponse()) {
                $response = $e->getResponse();
            } else {
                throw $e;
            }
        }
		
		$response = json_decode($response->getBody()->getContents(), true);
        return response($response, $response['resCode']);
    }
}
