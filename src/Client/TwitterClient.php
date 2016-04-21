<?php
/**
 * File TwitterClient.php 
 */

namespace Tebru\Dilbot\Client;

use Tebru\Retrofit\Annotation\FormUrlEncoded;
use Tebru\Retrofit\Annotation\Part;
use Tebru\Retrofit\Annotation\POST;
use Tebru\Retrofit\Annotation\Returns;

/**
 * Class TwitterClient
 *
 * @author Nate Brunette <n@tebru.net>
 *
 * @FormUrlEncoded()
 */
interface TwitterClient
{
    /**
     * Create a new twitter status
     *
     * @param string $mediaId
     * @param string $message
     * @return string
     *
     * @POST("/statuses/update.json")
     * @Part("media_ids", var="mediaId")
     * @Part("status", var="message")
     * @Returns("raw")
     */
    public function createStatusWithImage(string $mediaId, string $message);
}
