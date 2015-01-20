<?php
/**
 * File TwitterClient.php 
 */

namespace Tebru\DilbertPics\Client;

use Tebru\Retrofit\Annotation as Rest;

/**
 * Class TwitterClient
 *
 * @author Nate Brunette <n@tebru.net>
 */
interface TwitterClient
{
    /**
     * @Rest\POST("/statuses/update.json")
     * @Rest\Part("media_ids", var="mediaId")
     * @Rest\Part("status", var="message")
     * @Rest\Returns("raw")
     */
    public function createStatusWithImage($mediaId, $message);
}
