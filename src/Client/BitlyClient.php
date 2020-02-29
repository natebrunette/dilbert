<?php
/**
 * File BitlyClient.php 
 */

namespace Tebru\Dilbot\Client;

use Tebru\Retrofit\Annotation\Body;
use Tebru\Retrofit\Annotation\JsonBody;
use Tebru\Retrofit\Annotation\POST;
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
     * @POST("/v4/shorten")
     * @JsonBody()
     * @Body("body", jsonSerializable=true)
     * @Returns("array")
     *
     * @param array $body
     * @return array
     */
    public function shorten(array $body);
}
