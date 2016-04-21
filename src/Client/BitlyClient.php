<?php
/**
 * File BitlyClient.php 
 */

namespace Tebru\Dilbot\Client;

use Tebru\Retrofit\Annotation\GET;
use Tebru\Retrofit\Annotation\Query;
use Tebru\Retrofit\Annotation\Returns;

/**
 * Interface BitlyClient
 *
 * @author Nate Brunette <n@tebru.net>
 */
interface BitlyClient
{
    /**
     * Shorten a url
     *
     * @param string $longUrl
     * @return string
     *
     * @GET("/v3/shorten?format=txt")
     * @Query("longUrl")
     * @Returns("raw")
     */
    public function shorten(string $longUrl);
}
