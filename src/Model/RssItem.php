<?php
/**
 * File RssItem.php 
 */

namespace Tebru\DilbertPics\Model;

use DateTime;
use DateTimeZone;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Type;
use Monolog\Logger;
use Tebru\DilbertPics\Exception\NullPointerException;

/**
 * Class RssItem
 *
 * @author Nate Brunette <n@tebru.net>
 *
 * XmlNamespace(uri="http://rssnamespace.org/feedburner/ext/1.0", prefix="feedburner")
 */
class RssItem
{
    /**
     * @var string $tile
     *
     * @Type("string")
     */
    private $title;

    /**
     * @var string $link
     *
     * @Type("string")
     */
    private $link;

    /**
     * @var DateTime $publishedDate
     *
     * @Type("DateTime<'D, d M Y h:i:s T'>")
     * @SerializedName("pubDate")
     */
    private $publishedDate;

    /**
     * @throws NullPointerException
     * @return string
     */
    public function getTitle()
    {
        if (null === $this->title) {
            throw new NullPointerException('$title cannot be null', Logger::CRITICAL);
        }

        return $this->title;
    }

    /**
     * @param string $title
     * @return $this
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @throws NullPointerException
     * @return string
     */
    public function getLink()
    {
        if (null === $this->link) {
            throw new NullPointerException('$link cannot be null', Logger::CRITICAL);
        }

        return $this->link;
    }

    /**
     * @param string $link
     * @return $this
     */
    public function setLink($link)
    {
        $this->link = $link;

        return $this;
    }

    /**
     * @throws NullPointerException
     * @return DateTime
     */
    public function getPublishedDate()
    {
        if (null === $this->publishedDate) {
            throw new NullPointerException('$publishedDate cannot be null', Logger::CRITICAL);
        }

        return $this->publishedDate;
    }

    /**
     * @param DateTime $pubDate
     * @return $this
     */
    public function setPublishedDate(DateTime $pubDate)
    {
        $this->publishedDate = $pubDate;

        return $this;
    }

    /**
     * Set the timezone of the published date
     *
     * @param string $timezone
     * @return $this
     * @throws NullPointerException
     */
    public function setPublishedTimezone($timezone = 'UTC')
    {
        if (null === $this->publishedDate) {
            throw new NullPointerException('Could not set timezone because $publishedDate is empty', Logger::CRITICAL);
        }

        $this->publishedDate->setTimezone(new DateTimeZone($timezone));

        return $this;
    }
}
