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
use JMS\Serializer\SerializerInterface;
use Tebru\DilbertPics\Exception\NoNewPublicationException;
use Tebru\DilbertPics\Exception\NullPointerException;
use Tebru\DilbertPics\Exception\ResourceNotFoundException;
use Tebru\DilbertPics\Model\RssItem;
use XMLReader;

/**
 * Class DilbertClient
 *
 * Client to interact with dilbert rss feed and website
 *
 * @author Nate Brunette <n@tebru.net>
 */
class DilbertClient
{
    /**
     * RSS Url
     */
    const RSS_URL = 'http://feed.dilbert.com/dilbert/daily_strip?format=xml';

    /**
     * XML node to search for
     */
    const NODE_ITEM = 'item';

    /**
     * @var SerializerInterface $serializer
     */
    private $serializer;

    /**
     * @var ClientInterface $httpCient
     */
    private $httpCient;

    /**
     * Constructor
     *
     * @param SerializerInterface $serializer
     */
    public function __construct(SerializerInterface $serializer, ClientInterface $httpCient)
    {
        $this->serializer = $serializer;
        $this->httpCient = $httpCient;
    }

    /**
     * Get the latest item
     *
     * Will return false if there isn't a new item
     *
     * @return RssItem|bool
     * @throws NoNewPublicationException
     * @throws NullPointerException
     */
    public function getItem()
    {
        $reader = new XMLReader();
        $reader->open(self::RSS_URL);

        // read to the first item
        while ($reader->read() && $reader->name !== self::NODE_ITEM);

        $document = new DOMDocument();
        $xmlNode = simplexml_import_dom($document->importNode($reader->expand(), true));

        /** @var RssItem $rssItem */
        $rssItem = $this->serializer->deserialize($xmlNode->asXML(), RssItem::class, 'xml');
        $rssItem->setPublishedTimezone();

        if(!$this->hasNewPublication($rssItem)) {
            throw new NoNewPublicationException('There is not a new publication');
        }

        return $rssItem;
    }

    /**
     * Get image from website
     *
     * Returns the base64 encoded image
     *
     * @param RssItem $item
     *
     * @throws NullPointerException
     * @throws ResourceNotFoundException
     * @return string
     */
    public function getImage(RssItem $item)
    {
        $link = $item->getLink();

        // check if image exists
        if (!$this->pageExists($link)) {
            throw new ResourceNotFoundException('Image does not exist');
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
        $regex = '/(?:http:).+(?:strip(?:.sunday)\.gif)/';

        /** @var DOMElement $image */
        foreach ($images as $image) {
            if (preg_match($regex, $image->getAttribute('src'))) {
                $src = $image->getAttribute('src');
                break;
            }
        }

        if (null === $src) {
            throw new NullPointerException('Could not get image from page');
        }

        return base64_encode(file_get_contents($src));
    }

    /**
     * Ensure web page exists
     *
     * @param string $url
     *
     * @return bool
     */
    private function pageExists($url)
    {
        $response = $this->httpCient->head($url);
        $statusCode = $response->getStatusCode();

        return 200 === $statusCode;
    }

    /**
     * Determines whether we have a new item
     *
     * @todo Not indempotent - only checks if day matches; will need to add some sort of persistence to ensure no duplicates
     *
     * @param RssItem $rssItem
     *
     * @return bool
     *
     * @throws \Tebru\DilbertPics\Exception\NullPointerException
     */
    private function hasNewPublication(RssItem $rssItem)
    {
        $today = $this->getToday();

        $dateToday = $today->format('Y-m-d');
        $feedDateToday = $rssItem->getPublishedDate()->format('Y-m-d');

        return $dateToday === $feedDateToday;
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
