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
use Tebru\Executioner\Executor;
use Tebru\Executioner\Strategy\StaticWaitStrategy;
use Tebru\Executioner\Subscriber\LoggerSubscriber;
use Tebru\Executioner\Subscriber\WaitSubscriber;

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

        // get the latest rss item
        $rssExecutor = $this->createDilbertExecutor();
        $dilbertClient = $this->createDilbertClient();

        /** @var RssItem $rssItem */
        $rssItem = $rssExecutor->execute(
            30,
            function () use ($dilbertClient) {
                return $dilbertClient->getItem();
            }
        );

        // get the image from dilbert.com
        $imageExecutor = $this->createDilbertExecutor();
        $image = $imageExecutor->execute(
            30,
            function () use ($dilbertClient, $rssItem) {
                return $dilbertClient->getImage($rssItem);
            }
        );

        // upload image
        $twitterImageExecutor = $this->createTwitterExecutor();
        $twitterClient = $this->createTwitterClient($arguments);
        $mediaId = $twitterImageExecutor->execute(
            15,
            function () use ($twitterClient, $image) {
                // upload image
                return $twitterClient->uploadImage($image);
            }
        );

        // create short url
        $bitlyExecutor = $this->createBitlyExecutor($arguments, $rssItem->getLink());
        $bitlyClient = $this->createBitlyClient($arguments);
        $shortUrl = $bitlyExecutor->execute(
            2,
            function () use ($bitlyClient, $rssItem) {
                return $bitlyClient->shorten($rssItem->getLink());
            }
        );

        $twitterStatusExecutor = $this->createTwitterExecutor();
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
     * Create executor to poll rss feed
     *
     * @return Executor
     */
    private function createDilbertExecutor()
    {
        $loggerSubscriber = new LoggerSubscriber('rss', $this->logger);
        $waitSubscriber = new WaitSubscriber(new StaticWaitStrategy(60));

        $executor = new Executor();
        $executor->addSubscriber($loggerSubscriber);
        $executor->addSubscriber($waitSubscriber);

        return $executor;
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
     * Create executor for twitter
     *
     * @return Executor
     */
    private function createTwitterExecutor()
    {
        $loggerSubscriber = new LoggerSubscriber('twitter', $this->logger);

        $executor = new Executor();
        $executor->addSubscriber($loggerSubscriber);
        $executor->addSubscriber(new WaitSubscriber());

        return $executor;
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
     * Create executor for bitly
     *
     * @return Executor
     */
    private function createBitlyExecutor()
    {
        $loggerSubscriber = new LoggerSubscriber('bitly', $this->logger);
        $waitSubscriber = new WaitSubscriber(new StaticWaitStrategy(2));

        $executor = new Executor();
        $executor->addSubscriber($loggerSubscriber);
        $executor->addSubscriber($waitSubscriber);

        return $executor;
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
