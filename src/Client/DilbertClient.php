<?php
/**
 * File DilbertClient.php
 */

namespace Tebru\DilbertPics\Client;

use DateTime;
use DateTimeZone;
use DOMDocument;
use DOMElement;
use JMS\Serializer\SerializerInterface;
use Tebru\DilbertPics\Exception\NullPointerException;
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
     * Constructor
     *
     * @param SerializerInterface $serializer
     */
    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * Get the latest item
     *
     * Will return false if there isn't a new item
     *
     * @return RssItem|bool
     */
    public function getItem()
    {
        $reader = new XMLReader();
        $reader->open(self::RSS_URL);

        // read to the first item
        while ($reader->read() && $reader->name !== self::NODE_ITEM);

        $document = new DOMDocument();
        $xmlNode = simplexml_import_dom($document->importNode($reader->expand(), true));

        $rssItem = $this->serializer->deserialize($xmlNode->asXML(), RssItem::class, 'xml');

        if(!$this->hasNewPublication($rssItem)) {
            return false;
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
     * @return string
     *
     * @throws NullPointerException
     */
    public function getImage(RssItem $item)
    {
        $link = $item->getLink();
        $webpage = file_get_contents($link);
        $html = new DOMDocument();

        // disable notices
        libxml_use_internal_errors(true);
        $html->loadHTML($webpage);
        libxml_use_internal_errors(false);

        // look for all images and grab the comic based on regex of src attribute
        $images = $html->getElementsByTagName('img');
        $src = null;
        $regex = '/(?:http:).+(?:strip\.gif)/';

        /** @var DOMElement $image */
        foreach ($images as $image) {
            $src = $image->getAttribute('src');

            if (preg_match($regex, $src)) {
                break;
            }
        }

        if (null === $src) {
            throw new NullPointerException('Could not get image from page');
        }

        return base64_encode(file_get_contents($src));
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
