<?php
/**
 * File DilbertClient.php
 */

namespace Tebru\DilbertPics\Client;

use DateTime;
use DateTimeZone;
use DOMDocument;
use DOMElement;
use GuzzleHttp\ClientInterface;
use Tebru\DilbertPics\Exception\NullPointerException;
use Tebru\DilbertPics\Exception\ResourceNotFoundException;
use Tebru\Log\Loggable;

/**
 * Class DilbertClient
 *
 * Client to interact with dilbert rss feed and website
 *
 * @author Nate Brunette <n@tebru.net>
 */
class DilbertClient
{
    use Loggable;

    /**
     * Base url
     */
    const BASE_URL = 'http://dilbert.com/strip';

    /**
     * @var ClientInterface $httpCient
     */
    private $httpCient;

    /**
     * Constructor
     *
     * @param ClientInterface $httpCient
     */
    public function __construct(ClientInterface $httpCient)
    {
        $this->httpCient = $httpCient;
    }

    /**
     * Get image from website
     *
     * Returns the base64 encoded image
     *
     * @throws NullPointerException
     * @throws ResourceNotFoundException
     * @return string
     */
    public function getImage()
    {
        $link = $this->getUrl();

        // check if image exists
        if (!$this->pageExists($link)) {
            throw new ResourceNotFoundException('Web page does not exist');
        }

        $webpage = file_get_contents($link);
        $html = new DOMDocument();

        // disable notices
        libxml_use_internal_errors(true);
        $html->loadHTML($webpage);
        libxml_use_internal_errors(false);

        // look for all images and grab the comic based on regex of src attribute
        $images = $html->getElementsByTagName('img');
        $src = null;

        /** @var DOMElement $image */
        foreach ($images as $image) {
            if (strstr($image->getAttribute('src'), 'http://assets.amuniversal.com')) {
                $src = $image->getAttribute('src');
                break;
            }
        }

        $this->getLogger()->debug('Image source url: ' . $src);

        if (null === $src) {
            throw new NullPointerException('Could not get image from page');
        }

        // check if image exists
        if (!$this->imageExists($src)) {
            throw new ResourceNotFoundException('Image does not exist');
        }


        return base64_encode(file_get_contents($src));
    }

    /**
     * Get the page url
     *
     * @return string
     */
    public function getUrl()
    {
        $today = $this->getToday();
        $dateFormatted = $today->format('Y-m-d');
        $url = sprintf('%s/%s', self::BASE_URL, $dateFormatted);

        return $url;
    }

    /**
     * Check if web page exists
     *
     * @param string $url
     *
     * @return bool
     */
    private function pageExists($url)
    {
        $response = $this->httpCient->head($url);
        $responseCode = $response->getStatusCode();

        $this->getLogger()->debug('Status Code: ' . $responseCode, ['url' => $url]);

        return 200 === $responseCode;
    }

    /**
     * Ensure web page exists
     *
     * @param string $url
     *
     * @return bool
     */
    private function imageExists($url)
    {
        $response = $this->httpCient->head($url);
        $contentType = $response->getHeader('Content-Type');

        $this->getLogger()->debug('Content type' . $contentType, ['url' => $url]);

        return 'image/gif' === $contentType;
    }

    /**
     * Gets the current date in UTC
     *
     * @return DateTime
     */
    private function getToday()
    {
        return new DateTime('now', new DateTimeZone('UTC'));
    }
} 
