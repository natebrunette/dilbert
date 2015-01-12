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
use JMS\Serializer\SerializerBuilder;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Tebru\DilbertPics\Client\BitlyClient;
use Tebru\DilbertPics\Client\DilbertClient;
use Tebru\DilbertPics\Client\TwitterClient;
use Tebru\DilbertPics\Exception\InvalidArgumentException;
use Tebru\DilbertPics\Model\RssItem;
use Tebru\Executioner\Factory\ExecutorFactory;
use Tebru\Executioner\Strategy\ExponentialBackoffStrategy;

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

        $executorFactory = new ExecutorFactory();

        // get the latest rss item
        $rssExecutor = $executorFactory->make('rss', $this->logger, 60);
        $dilbertClient = $this->createDilbertClient();

        /** @var RssItem $rssItem */
        $rssItem = $rssExecutor->execute(
            30,
            function () use ($dilbertClient) {
                return $dilbertClient->getItem();
            }
        );

        // get the image from dilbert.com
        $imageExecutor = $executorFactory->make('image', $this->logger, 60);
        $image = $imageExecutor->execute(
            30,
            function () use ($dilbertClient, $rssItem) {
                return $dilbertClient->getImage($rssItem);
            }
        );

        // upload image
        $twitterImageExecutor = $executorFactory->make('twitter-image', $this->logger, new ExponentialBackoffStrategy());
        $twitterClient = $this->createTwitterClient($arguments);
        $mediaId = $twitterImageExecutor->execute(
            15,
            function () use ($twitterClient, $image) {
                return $twitterClient->uploadImage($image);
            }
        );

        // create short url
        $bitlyExecutor = $executorFactory->make('bitly', $this->logger, 2);
        $bitlyClient = $this->createBitlyClient($arguments);
        $shortUrl = $bitlyExecutor->execute(
            2,
            function () use ($bitlyClient, $rssItem) {
                return $bitlyClient->shorten($rssItem->getLink());
            }
        );

        $twitterStatusExecutor = $executorFactory->make('twitter-status', $this->logger, new ExponentialBackoffStrategy());
        $twitterStatusExecutor->execute(
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
        $serializer = SerializerBuilder::create()->build();
        $dilbertHttpClient = new Client();

        return new DilbertClient($serializer, $dilbertHttpClient);
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

        return new TwitterClient($httpClient);
    }

    /**
     * Create a bitly client
     *
     * @param array $arguments
     * @return BitlyClient
     */
    private function createBitlyClient(array $arguments)
    {
        $bitlyHttpClient = new Client(['debug' => true]);

        return new BitlyClient($bitlyHttpClient, $arguments[ArgumentEnum::BITLY_AUTH_TOKEN]);
    }
} 
