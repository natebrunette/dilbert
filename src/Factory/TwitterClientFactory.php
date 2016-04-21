<?php
/*
 * Copyright (c) Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Dilbot\Factory;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Subscriber\Oauth\Oauth1;
use Psr\Log\LoggerInterface;
use Tebru\Dilbot\Client\TwitterClient;
use Tebru\Dilbot\Client\TwitterUploadClient;
use Tebru\Retrofit\Adapter\HttpClientAdapter;
use Tebru\Retrofit\Adapter\Rest\RestAdapter;
use Tebru\Retrofit\Exception\RetrofitException;
use Tebru\Retrofit\HttpClient\Adapter\Guzzle\GuzzleV6ClientAdapter;

/**
 * Class TwitterClientFactory
 *
 * @author Nate Brunette <n@tebru.net>
 */
class TwitterClientFactory
{
    /**
     * Create a twitter client
     *
     * @param string $consumerKey
     * @param string $consumerSecret
     * @param string $accessToken
     * @param string $accessTokenSecret
     * @param LoggerInterface $logger
     * @return TwitterClient
     * @throws RetrofitException
     */
    public static function makeTwitterClient(
        string $consumerKey,
        string $consumerSecret,
        string $accessToken,
        string $accessTokenSecret,
        LoggerInterface $logger
    ): TwitterClient
    {
        $clientAdapter = self::getTwitterClientAdapter($consumerKey, $consumerSecret, $accessToken, $accessTokenSecret);

        return RestAdapter::builder()
            ->setBaseUrl('https://api.twitter.com/1.1')
            ->setClientAdapter($clientAdapter)
            ->setLogger($logger)
            ->build()
            ->create(TwitterClient::class);

    }

    /**
     * Create a twitter upload client
     *
     * @param string $consumerKey
     * @param string $consumerSecret
     * @param string $accessToken
     * @param string $accessTokenSecret
     * @param LoggerInterface $logger
     * @return TwitterUploadClient
     * @throws RetrofitException
     */
    public static function makeTwitterUploadClient(
        string $consumerKey,
        string $consumerSecret,
        string $accessToken,
        string $accessTokenSecret,
        LoggerInterface $logger
    ): TwitterUploadClient
    {
        $clientAdapter = self::getTwitterClientAdapter($consumerKey, $consumerSecret, $accessToken, $accessTokenSecret);

        return RestAdapter::builder()
            ->setBaseUrl('https://upload.twitter.com/1.1')
            ->setClientAdapter($clientAdapter)
            ->setLogger($logger)
            ->build()
            ->create(TwitterUploadClient::class);
    }

    /**
     * Create the guzzle client adapter
     *
     * @param string $consumerKey
     * @param string $consumerSecret
     * @param string $accessToken
     * @param string $accessTokenSecret
     * @return HttpClientAdapter
     */
    private static function getTwitterClientAdapter(
        string $consumerKey,
        string $consumerSecret,
        string $accessToken,
        string $accessTokenSecret
    ): HttpClientAdapter
    {
        $stack = HandlerStack::create();

        $middleware = new Oauth1([
            'consumer_key' => $consumerKey,
            'consumer_secret' => $consumerSecret,
            'token' => $accessToken,
            'token_secret' => $accessTokenSecret,
        ]);
        $stack->push($middleware);

        return new GuzzleV6ClientAdapter(new Client(['handler' => $stack, 'auth' => 'oauth']));
    }
}
