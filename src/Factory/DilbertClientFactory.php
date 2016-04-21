<?php
/*
 * Copyright (c) Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Dilbot\Factory;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Tebru\Dilbot\Client\DilbertClient;

/**
 * Class DilbertClientFactory
 *
 * @author Nate Brunette <n@tebru.net>
 */
class DilbertClientFactory
{
    /**
     * Create a Dilbert Client
     *
     * @param LoggerInterface $logger
     * @return DilbertClient
     */
    public static function make(LoggerInterface $logger): DilbertClient
    {
        $client = new DilbertClient(new Client());
        $client->setLogger($logger);

        return $client;
    }
}
