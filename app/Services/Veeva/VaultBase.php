<?php

namespace App\Services\Veeva;

use GuzzleHttp\Client;

// class VaultBase extends VaultConfig implements VaultInterface
class VaultBase extends VaultConfig
{

    public $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * makeHeadersForApiCall
     *
     * @param  array $data
     * @return array
     */
    public function makeHeadersForApiCall($data = [])
    {
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
            'X-VaultAPI-DescribeQuery: true',
        ];

        // $headers = [
        //     'Content-Type: application/json',
        //     'Accept: application/json',
        //     'X-VaultAPI-DescribeQuery: true',
        // ];

        if (!empty($data) && isset($data['sessionId'])) {
            $headers[] = 'Authorization: ' . $data['sessionId'];
            // unset($headers);
            // $headers = [
            //     'Content-Type: application/json',
            //     'Accept: application/json',
            //     'X-VaultAPI-DescribeQuery: true',
            //     'Authorization: ' . $data['sessionId']
            // ];
        }

        return $headers;
    }

    /**
     * makeHeadersForApiCallAuth
     *
     * @param  array $data
     * @return array
     */
    public function makeHeadersForApiCallAuth($data = [])
    {
        $headers = [
            'Content-Type'=> 'application/x-www-form-urlencoded',
            'Accept'=> 'application/json',
            'X-VaultAPI-DescribeQuery'=> 'true',
            'Authorization' => 'Bearer ' . $data['sessionId']
        ];
        return $headers;
    }

    /**
     * makeSessionId
     *
     * @return array
     */
    public function makeSessionId()
    {
        // get sesssion ID
        $base_url = $this->apiUrl;
        $url = $base_url . $this->apiVersion . $this->auth;
        $query = "username=$this->username&password=$this->password";
        // $arg = [];
        // $arg['source'] = 'session';
        $headers = $this->makeHeadersForApiCall();

        $responseArr = $this->call_curl($url, $query, $headers, $base_url, 1);
        return $responseArr;
    }

    public function makeSessionIdForGuzzle()
    {
        try {
            $url = $this->apiUrl . $this->apiVersion . $this->auth;
            $response = $this->client->request('POST', $url, [
                'form_params' => [
                    'username' => $this->username,
                    'password' => $this->password,
                ],
                'headers' => $this->makeHeadersForApiCall(),
            ]);

            $body = $response->getBody()->getContents();
            $decoded = json_decode($body, true);
            return $decoded;
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // Handle exceptions here
            return ['error' => $e->getMessage()];
        }
    }

    // CURL call
    public function call_curl($url, $query, $headers, $base_url, $post)
    {
        //$ids_to_return;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_URL, $url);
        if ($post == 1) {
            curl_setopt($ch, CURLOPT_POST, 1);
        }
        if ($query != "0") { //indicates we are getting more data via next page
            curl_setopt($ch, CURLOPT_POSTFIELDS, $query);  //Post Fields
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); // this results 0 every time
        $response = curl_exec($ch);
        if ($response === false) {
            $response = curl_error($ch);
        }
        $decoded = json_decode($response, true);
        return $decoded;
    }

    public function getGuzzleRequest($url, $query, $headers)
    {
        try {
            $response = $this->client->request('GET', $url, [
                'query' => $query,
                'headers' => $headers
            ]);

            $body = $response->getBody()->getContents();
            $decoded = json_decode($body, true);

            return $decoded;
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // Handle exceptions here
            return ['error' => $e->getMessage()];
        }
    }
}