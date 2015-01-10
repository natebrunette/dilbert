<?php
/**
 * File BitlyClient.php 
 */

namespace Tebru\DilbertPics\Client;

use GuzzleHttp\ClientInterface;

/**
 * Class BitlyClient
 *
 * Interacts with the Bitly API
 *
 * @author Nate Brunette <n@tebru.net>
 */
class BitlyClient
{
    /**
     * API base url
     */
    const BASE_URL = 'https://api-ssl.bitly.com';

    /**#@+
     * Endpoints
     */
    const ENDPOINT_SHORTEN = 'v3/shorten';
    /**#@-*/

    /**
     * @var ClientInterface $httpclient
     */
    private $httpclient;

    /**
     * The api auth token
     *
     * @var string $authToken
     */
    private $authToken;

    /**
     * Constructor
     *
     * @param string $authToken
     */
    public function __construct(ClientInterface $httpclient, $authToken)
    {
        $this->httpclient = $httpclient;
        $this->authToken = $authToken;
    }

    /**
     * Get the short version of a long url
     *
     * @param string $longUrl
     *
     * @return string
     */
    public function shorten($longUrl)
    {
        $url = $this->createUrl(self::ENDPOINT_SHORTEN);
        $options['query'] = ['access_token' => $this->authToken, 'longUrl' => $longUrl, 'format' => 'txt'];
        $response = $this->httpclient->get($url,$options);

        // because we're formatting with 'txt', the response is just the url
        $body = (string)$response->getBody();

        return $body;
    }

    /**
     * Create an request url
     *
     * @param string $endpoint
     *
     * @return string
     */
    private function createUrl($endpoint)
    {
        return sprintf(self::BASE_URL . '/' . $endpoint);
    }
} 
