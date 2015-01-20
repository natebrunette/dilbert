<?php
/**
 * File Application.php 
 */

namespace Tebru\DilbertPics;

use DateTime;
use GuzzleHttp\Client;
use GuzzleHttp\Subscriber\Log\Formatter;
use GuzzleHttp\Subscriber\Log\LogSubscriber;
use GuzzleHttp\Subscriber\Oauth\Oauth1;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Tebru\DilbertPics\Client\BitlyClient;
use Tebru\DilbertPics\Client\DilbertClient;
use Tebru\DilbertPics\Client\TwitterClient;
use Tebru\DilbertPics\Client\TwitterUploadClient;
use Tebru\DilbertPics\Exception\InvalidArgumentException;
use Tebru\DilbertPics\Exception\NullPointerException;
use Tebru\Executioner\Factory\ExecutorFactory;
use Tebru\Executioner\Strategy\ExponentialBackoffStrategy;
use Tebru\Retrofit\Adapter\RestAdapter;
use Tebru\Retrofit\RequestInterceptor;

/**
 * Class Application
 *
 * Main application class
 *
 * @author Nate Brunette <n@tebru.net>
 */
class Application
{
    /**
     * Command line arguments
     *
     * @var ArgumentEnum $arguments
     */
    private $arguments;

    /**
     * @var LoggerInterface $logger
     */
    private $logger;

    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->arguments = new ArgumentEnum();
    }

    /**
     * Start point
     *
     * @param array $arguments
     * @throws Exception\NullPointerException
     * @throws InvalidArgumentException
     */
    public function main(array $arguments)
    {
        // remove script argument
        array_shift($arguments);

        if ($this->arguments->getTotal() !== count($arguments)) {
            throw new InvalidArgumentException(
                sprintf('Too few arguments passed to application. Expected %d, got %s', $this->arguments->getTotal(), count($arguments)),
                Logger::ERROR
            );
        }

        // create executor
        $executor = ExecutorFactory::make();

        // get the image from the web page
        $executor->setWait(60);
        $executor->setLogger('image', $this->logger);

        $dilbertClient = $this->createDilbertClient();

        $this->logger->info('Starting image fetching');
        $image = $executor->execute(
            30,
            function () use ($dilbertClient) {
                return $dilbertClient->getImage();
            }
        );

        // upload image
        $executor->updateLoggerName('twitter-image');
        $executor->setWaitStrategy(new ExponentialBackoffStrategy());

        $twitterUploadClient = $this->createTwitterUploadClient($arguments);
        $this->logger->info('Starting Twitter image uploading');
        $response = $executor->execute(
            15,
            function () use ($twitterUploadClient, $image) {
                return $twitterUploadClient->uploadImage($image);
            }
        );

        if (!isset($response['media_id'])) {
            throw new NullPointerException('Media id not set on response');
        }

        $mediaId = $response['media_id'];

        // create short url
        $executor->updateLoggerName('bitly');
        $executor->setWait(2);

        $bitlyClient = $this->createBitlyClient($arguments);
        $this->logger->info('Starting URL shortening');
        $shortUrl = $executor->execute(
            2,
            function () use ($bitlyClient, $dilbertClient) {
                return $bitlyClient->shorten($dilbertClient->getUrl());
            }
        );

        $executor->updateLoggerName('twitter-status');
        $executor->setWaitStrategy(new ExponentialBackoffStrategy());

        $twitterClient = $this->createTwitterClient($arguments);
        $this->logger->info('Starting Twitter status update');
        $executor->execute(
            15,
            function () use ($twitterClient, $mediaId, $shortUrl) {
                // create message
                $today = new DateTime();
                $message = sprintf('Dilbert comic for %s %s', $today->format('M jS, Y'), $shortUrl);

                $twitterClient->createStatusWithImage($mediaId, $message);
            }
        );
    }

    /**
     * Create a dilbert client
     *
     * @return DilbertClient
     */
    private function createDilbertClient()
    {
        $client = new DilbertClient(new Client());
        $client->setLogger($this->logger);

        return $client;
    }

    /**
     * Create twitter upload client
     *
     * @param array $arguments
     *
     * @return TwitterUploadClient
     */
    private function createTwitterUploadClient(array $arguments)
    {
        $httpClient = $this->getTwitterClient($arguments);

        return RestAdapter::builder()
            ->setBaseUrl('https://upload.twitter.com/1.1')
            ->setHttpClient($httpClient)
            ->build()
            ->create(TwitterUploadClient::class);
    }

    /**
     * Create twitter client
     *
     * @param array $arguments
     *
     * @return TwitterClient
     */
    private function createTwitterClient(array $arguments)
    {
        $httpClient = $this->getTwitterClient($arguments);

        return RestAdapter::builder()
            ->setBaseUrl('https://api.twitter.com/1.1')
            ->setHttpClient($httpClient)
            ->build()
            ->create(TwitterClient::class);
    }

    /**
     * Create twitter http client
     *
     * @param array $arguments
     * @return Client
     */
    private function getTwitterClient(array $arguments)
    {
        $consumerKey = $arguments[ArgumentEnum::TWITTER_CONSUMER_KEY];
        $consumerSecret = $arguments[ArgumentEnum::TWITTER_CONSUMER_SECRET];
        $accessToken = $arguments[ArgumentEnum::TWITTER_TOKEN];
        $accessTokenSecret = $arguments[ArgumentEnum::TWITTER_TOKEN_SECRET];

        $oauth = new Oauth1([
            'consumer_key' => $consumerKey,
            'consumer_secret' => $consumerSecret,
            'token' => $accessToken,
            'token_secret' => $accessTokenSecret
        ]);

        $logSubscriber = new LogSubscriber($this->logger, Formatter::DEBUG);

        $httpClient = new Client(['defaults' => ['auth' => 'oauth'], 'debug' => true]);
        $httpClient->getEmitter()->attach($oauth);
        $httpClient->getEmitter()->attach($logSubscriber);

        return $httpClient;

    }

    /**
     * Create a bitly client
     *
     * @param array $arguments
     * @return BitlyClient
     */
    private function createBitlyClient(array $arguments)
    {
        $requestInterceptor = new RequestInterceptor();
        $requestInterceptor->addQuery('access_token', $arguments[ArgumentEnum::BITLY_AUTH_TOKEN]);

        $logSubscriber = new LogSubscriber($this->logger, Formatter::DEBUG);

        $httpClient = new Client(['debug' => true]);
        $httpClient->getEmitter()->attach($logSubscriber);

        return RestAdapter::builder()
            ->setBaseUrl('https://api-ssl.bitly.com')
            ->setHttpClient($httpClient)
            ->setRequestInterceptor($requestInterceptor)
            ->build()
            ->create(BitlyClient::class);
    }
} 
