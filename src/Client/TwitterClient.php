<?php
/**
 * File TwitterClient.php
 */

namespace Tebru\DilbertPics\Client;

use GuzzleHttp\ClientInterface;
use Tebru\DilbertPics\Exception\NullPointerException;

/**
 * Class TwitterClient
 *
 * Client for interacting with the twitter api
 *
 * @author Nate Brunette <n@tebru.net>
 */
class TwitterClient
{
    /**
     * Upload api base url
     */
    const UPLOAD_BASE_URL = 'https://upload.twitter.com';

    /**
     * Api base url
     */
    const API_BASE_URL = 'https://api.twitter.com';

    /**
     * Api version
     */
    const API_VERSION = '1.1';

    /**#@+
     * Endpoints
     */
    const ENDPOINT_MEDIA = 'media/upload.json';
    const ENDPOINT_STATUS_UPDATE = 'statuses/update.json';
    /**#@-*/

    /**
     * Http Client
     *
     * @var ClientInterface $httpClient
     */
    private $httpClient;

    /**
     * Constructor
     *
     * @var ClientInterface $httpClient
     */

    public function __construct(ClientInterface $client)
    {
        $this->httpClient = $client;
    }

    /**
     * Upload an image and return the resulting media id from twitter
     *
     * @param string $image
     *
     * @return string
     *
     * @throws NullPointerException
     */
    public function uploadImage($image)
    {
        $url = $this->createUrl(self::UPLOAD_BASE_URL, self::ENDPOINT_MEDIA);
        $options['body'] = ['media' => $image];
        $response = $this->httpClient->post($url, $options);
        $body = (string)$response->getBody();
        $response = json_decode($body, true);

        if (!isset($response['media_id'])) {
            throw new NullPointerException('Media id not set on response');
        }

        $mediaId = $response['media_id'];

        return $mediaId;
    }

    /**
     * Create a status with image attached
     *
     * @param string $mediaId The image id
     * @param string $message The status message
     */
    public function createStatusWithImage($mediaId, $message)
    {
        $url = $this->createUrl(self::API_BASE_URL, self::ENDPOINT_STATUS_UPDATE);
        $options['body'] = ['media_ids' => $mediaId, 'status' => $message];
        $this->httpClient->post($url, $options);
    }

    /**
     * Create an api url
     *
     * @param string $baseUrl
     * @param string $endpoint
     *
     * @return string
     */
    private function createUrl($baseUrl, $endpoint)
    {
        return sprintf('%s/%s/%s', $baseUrl, self::API_VERSION, $endpoint);
    }
}
