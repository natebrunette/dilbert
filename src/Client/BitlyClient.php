<?php
/**
 * File BitlyClient.php 
 */

namespace Tebru\DilbertPics\Client;

use Tebru\Retrofit\Annotation as Rest;

/**
 * Interface BitlyClient
 *
 * @author Nate Brunette <n@tebru.net>
 * @Rest\Query("authToken")
 */
interface BitlyClient
{
    /**
     * @param $longUrl
     * @return mixed
     *
     * @Rest\GET("/v3/shorten?format=txt")
     * @Rest\Query("longUrl")
     * @Rest\Returns("raw")
     */
    public function shorten($longUrl);
}
