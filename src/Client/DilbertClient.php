<?php
/**
 * File DilbertClient.php
 */

namespace Tebru\Dilbot\Client;

use DateTime;
use DateTimeZone;
use DOMDocument;
use DOMElement;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use InvalidArgumentException;
use Psr\Log\LoggerAwareTrait;
use Tebru\Dilbot\Exception\NullPointerException;
use Tebru\Dilbot\Exception\ResourceNotFoundException;

use function Tebru\assert;

/**
 * Class DilbertClient
 *
 * Client to interact with dilbert rss feed and website
 *
 * @author Nate Brunette <n@tebru.net>
 */
class DilbertClient
{
    use LoggerAwareTrait;

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
     * @throws InvalidArgumentException
     * @throws GuzzleException
     */
    public function getImage(): string
    {
        $link = $this->getUrl();

        assert($this->resourceExists($link), new ResourceNotFoundException('Web page does not exist'));

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
            if (false !== strpos($image->getAttribute('src'), '//assets.amuniversal.com')) {
                $src = 'https:'.$image->getAttribute('src');
                break;
            }
        }

        $this->logger->debug('Image source url: ' . $src);

        assert(null !== $src, new NullPointerException('Could not get image from page'));
        assert($this->resourceExists($src), new ResourceNotFoundException('Image does not exist'));

        return base64_encode(file_get_contents($src));
    }

    /**
     * Get the page url
     *
     * @return string
     */
    public function getUrl(): string
    {
        $today = $this->getToday();
        $dateFormatted = $today->format('Y-m-d');

        return sprintf('%s/%s', self::BASE_URL, $dateFormatted);
    }

    /**
     * Check if web page exists
     *
     * @param string $url
     * @return bool
     * @throws InvalidArgumentException
     * @throws GuzzleException
     */
    private function resourceExists(string $url)
    {
        $response = $this->httpCient->send(new Request('HEAD', $url));
        $responseCode = $response->getStatusCode();

        $this->logger->debug('Status Code: ' . $responseCode, ['url' => $url]);

        return 200 === $responseCode;
    }

    /**
     * Gets the current date in UTC
     *
     * @return DateTime
     */
    private function getToday(): DateTime
    {
        return new DateTime('now', new DateTimeZone('UTC'));
    }
} 
