<?php
/**
 * File ArgumentEnum.php 
 */

namespace Tebru\DilbertPics;

use SplEnum;

/**
 * Class ArgumentEnum
 *
 * @author Nate Brunette <n@tebru.net>
 */
class ArgumentEnum extends SplEnum
{
    const TWITTER_CONSUMER_KEY = 0;
    const TWITTER_CONSUMER_SECRET = 1;
    const TWITTER_TOKEN = 2;
    const TWITTER_TOKEN_SECRET = 3;
    const BITLY_AUTH_TOKEN = 4;

    /**
     * Get total arguments
     *
     * @return int
     */
    public function getTotal()
    {
        return count($this->getConstList());
    }
}
