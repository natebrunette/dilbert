<?php
/*
 * Copyright (c) Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Dilbot\Factory;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Tebru\Dilbot\Client\BitlyClient;
use Tebru\Dilbot\Subscriber\BitlyRequestSubscriber;
use Tebru\Retrofit\Adapter\Rest\RestAdapter;
use Tebru\Retrofit\Exception\RetrofitException;
use Tebru\Retrofit\HttpClient\Adapter\Guzzle\GuzzleV6ClientAdapter;

/**
 * Class BitlyClientFactory
 *
 * @author Nate Brunette <n@tebru.net>
 */
class BitlyClientFactory
{
    /**
     * Make a bitly api client
     *
     * @param string $accessToken
     * @param LoggerInterface $logger
     * @return BitlyClient
     * @throws RetrofitException
     */
    public static function make(string $accessToken, LoggerInterface $logger): BitlyClient
    {
        $httpClient = new Client();
        $clientAdapter = new GuzzleV6ClientAdapter($httpClient);
        $subscriber = new BitlyRequestSubscriber($accessToken);

        return RestAdapter::builder()
            ->setBaseUrl('https://api-ssl.bitly.com')
            ->setClientAdapter($clientAdapter)
            ->addSubscriber($subscriber)
            ->setLogger($logger)
            ->build()
            ->create(BitlyClient::class);
    }
}
