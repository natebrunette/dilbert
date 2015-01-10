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
use Tebru\Executioner\Logger\ExceptionLogger;
use Tebru\Executioner\Strategy\Termination\TimeBoundTerminationStrategy;
use Tebru\Executioner\Strategy\Wait\ExponentialWaitStrategy;
use Tebru\Executioner\Strategy\Wait\FibonacciWaitStrategy;

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

        $serializer = SerializerBuilder::create()->build();
        $dilbertClient = new DilbertClient($serializer);

        $rssExecutor = $this->createRssExecutor();

        // get the latest rss item
        /** @var RssItem $rssItem */
        $rssItem = $rssExecutor->execute(function () use ($dilbertClient) { return $dilbertClient->getItem(); });
        $rssItem->setPublishedTimezone();

        // get the image from dilbert.com
        $imageExecutor = $this->createImageExecutor();
        $image = $imageExecutor->execute(function () use ($dilbertClient, $rssItem) { return $dilbertClient->getImage($rssItem); });

        // create new status
        $bitlyExecutor = $this->createBitlyExecutor($arguments, $rssItem->getLink());
        $twitterExecutor = $this->createTwitterExecutor();
        $twitterClient = $this->createTwitterClient($arguments);
        $twitterExecutor->execute(
            function () use ($twitterClient, $image, $bitlyExecutor, $rssItem) {
                // upload image
                $mediaId = $twitterClient->uploadImage($image);

                // attempt to get shortened url
                $shortUrl = $bitlyExecutor->execute();

                // create status
                $today = new DateTime();
                $message = sprintf('Dilbert comic for %s %s', $today->format('M jS, Y'), $shortUrl);
                $twitterClient->createStatusWithImage($mediaId, $message);
            }
        );
    }

    public function errorHandler()
    {

    }

    /**
     * Create executor to poll rss feed
     *
     * @return Executor
     */
    private function createRssExecutor()
    {
        $logger = new ExceptionLogger($this->logger, Logger::ERROR, 'Unable to get RSS Item');
        $waitStrategy = new FibonacciWaitStrategy(60, 0);
        $terminationStrategy = new TimeBoundTerminationStrategy(7200);

        $executor = new Executor($logger, $waitStrategy, $terminationStrategy);
        $executor->setRetryableReturns([false]);

        return $executor;
    }

    /**
     * Create executor to get image from dilbert.com
     *
     * @return Executor
     */
    private function createImageExecutor()
    {
        $logger = new ExceptionLogger($this->logger, Logger::CRITICAL, 'Unable to get image');

        $executor = new Executor($logger);
        $executor->sleep(5)->limit(3);

        return $executor;
    }

    /**
     * Create executor for twitter
     *
     * @return Executor
     */
    private function createTwitterExecutor()
    {
        $logger = new ExceptionLogger($this->logger, Logger::CRITICAL, 'Could not create twitter status');
        $waitStrategy = new ExponentialWaitStrategy();

        $executor = new Executor($logger, $waitStrategy);
        $executor->limit(15);

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
     * @param array $arguments
     * @param string $link
     *
     * @return Executor
     */
    private function createBitlyExecutor(array $arguments, $link)
    {
        $logger = new ExceptionLogger($this->logger, Logger::CRITICAL, 'Could not create bitly link');

        $httpClient = new Client(['debug' => true]);
        $bitlyClient = new BitlyClient($httpClient, $arguments[ArgumentEnum::BITLY_AUTH_TOKEN]);
        $attemptor = function () use ($bitlyClient, $link) { return $bitlyClient->shorten($link); };

        $executor = new Executor($logger, null, null, $attemptor);
        $executor->sleep(2);
        $executor->limit(2);

        return $executor;
    }
} 
