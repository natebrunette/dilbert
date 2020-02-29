<?php
/*
 * Copyright (c) Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Dilbot\Command;

use DateTime;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use LogicException;
use Monolog\Handler\NativeMailerHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Tebru\Dilbot\Client\BitlyClient;
use Tebru\Dilbot\Client\DilbertClient;
use Tebru\Dilbot\Client\TwitterClient;
use Tebru\Dilbot\Client\TwitterUploadClient;
use Tebru\Dilbot\Exception\NullPointerException;
use Tebru\Dilbot\Exception\ResourceNotFoundException;
use Tebru\Dilbot\Factory\BitlyClientFactory;
use Tebru\Dilbot\Factory\DilbertClientFactory;
use Tebru\Dilbot\Factory\TwitterClientFactory;
use Tebru\Executioner\Exception\FailedException;
use Tebru\Executioner\Factory\ExecutorFactory;
use Tebru\Executioner\Strategy\ExponentialBackoffStrategy;
use Tebru\Retrofit\Exception\RetrofitException;

use function Tebru\assertArrayKeyExists;

/**
 * Class RunCommand
 *
 * @author Nate Brunette <n@tebru.net>
 */
class RunCommand extends Command
{
    const NAME = 'run';

    const TWITTER_CONSUMER_KEY = 'twitter-consumer-key';
    const TWITTER_CONSUMER_SECRET = 'twitter-consumer-secret';
    const TWITTER_ACCESS_TOKEN = 'twitter-access-token';
    const TWITTER_ACCESS_TOKEN_SECRET = 'twitter-access-token-secret';
    const BITLY_AUTH_TOKEN = 'bitly-auth-token';

    const LOG_NAME = 'dilbot';
    const LOG_LOCATION = '/../../var/log/dilbot.log';
    const LOG_EMAIL_TO = 'n+dilberterror@tebru.net';
    const LOG_MESSAGE = 'An error occurred';
    const LOG_EMAIL_FROM = 'system@dilbertpics.com';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var DilbertClient
     */
    private $dilbertClient;

    /**
     * @var TwitterClient
     */
    private $twitterClient;

    /**
     * @var TwitterUploadClient
     */
    private $twitterUploadClient;

    /**
     * @var BitlyClient
     */
    private $bitlyClient;

    /**
     * Configures the current command.
     */
    protected function configure()
    {
        $this->addOption(self::TWITTER_CONSUMER_KEY, null, InputOption::VALUE_REQUIRED, 'Twitter Consumer Key');
        $this->addOption(self::TWITTER_CONSUMER_SECRET, null, InputOption::VALUE_REQUIRED, 'Twitter Consumer Secret');
        $this->addOption(self::TWITTER_ACCESS_TOKEN, null, InputOption::VALUE_REQUIRED, 'Twitter Access Token');
        $this->addOption(self::TWITTER_ACCESS_TOKEN_SECRET, null, InputOption::VALUE_REQUIRED, 'Twitter Access Token Secret');
        $this->addOption(self::BITLY_AUTH_TOKEN, null, InputOption::VALUE_REQUIRED, 'Bitly Auth Token');
    }

    /**
     * Initializes the command just after the input has been validated.
     *
     * This is mainly useful when a lot of commands extends one main command
     * where some things need to be initialized based on the input arguments and options.
     *
     * @param InputInterface $input An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     * @throws RetrofitException
     * @throws Exception
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $twitterConsumerKey = $input->getOption(self::TWITTER_CONSUMER_KEY);
        $twitterConsumerSecret = $input->getOption(self::TWITTER_CONSUMER_SECRET);
        $twitterAccessToken = $input->getOption(self::TWITTER_ACCESS_TOKEN);
        $twitterAccessTokenSecret = $input->getOption(self::TWITTER_ACCESS_TOKEN_SECRET);
        $bitlyAuthToken = $input->getOption(self::BITLY_AUTH_TOKEN);

        $this->logger = $this->getLogger();

        $this->dilbertClient = DilbertClientFactory::make($this->logger);
        $this->twitterClient = TwitterClientFactory::makeTwitterClient(
            $twitterConsumerKey,
            $twitterConsumerSecret,
            $twitterAccessToken,
            $twitterAccessTokenSecret,
            $this->logger
        );
        $this->twitterUploadClient = TwitterClientFactory::makeTwitterUploadClient(
            $twitterConsumerKey,
            $twitterConsumerSecret,
            $twitterAccessToken,
            $twitterAccessTokenSecret,
            $this->logger
        );
        $this->bitlyClient = BitlyClientFactory::make($bitlyAuthToken, $this->logger);
    }

    /**
     * Executes the current command.
     *
     * This method is not abstract because you can use this class
     * as a concrete class. In this case, instead of defining the
     * execute() method, you set the code to execute by passing
     * a Closure to the setCode() method.
     *
     * @param InputInterface $input An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     *
     * @return null|int null or 0 if everything went fine, or an error code
     * @throws InvalidArgumentException
     * @throws GuzzleException
     * @throws FailedException
     * @throws ResourceNotFoundException
     * @throws NullPointerException
     *
     * @throws LogicException When this abstract method is not implemented
     *
     * @see setCode()
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $image = $this->getImage();
        $mediaId = $this->uploadImage($image);
        $shortUrl = $this->createShortUrl();
        $this->createTwitterStatus($mediaId, $shortUrl);
    }

    /**
     * Get the image from dilbert.com
     *
     * @return string
     * @throws InvalidArgumentException
     * @throws GuzzleException
     * @throws ResourceNotFoundException
     * @throws NullPointerException
     * @throws FailedException
     */
    private function getImage(): string
    {
        $executor = ExecutorFactory::make('image', $this->logger, 60);

        return $executor->execute(30, function () {
            return $this->dilbertClient->getImage();
        });
    }

    /**
     * Upload an image and return media id
     *
     * @param string $image
     * @return string
     * @throws FailedException
     */
    private function uploadImage(string $image): string
    {
        $executor = ExecutorFactory::make('twitter-image', $this->logger, new ExponentialBackoffStrategy());

        $response = $executor->execute(15, function () use ($image) {
            return $this->twitterUploadClient->uploadImage($image);
        });

        assertArrayKeyExists('media_id', $response, 'Media id not set on response');

        return $response['media_id'];
    }

    /**
     * Create bitly shortened url
     *
     * @return string
     * @throws FailedException
     */
    private function createShortUrl(): string
    {
        $executor = ExecutorFactory::make('image', $this->logger, 2);

        return $executor->execute(2, function () {
            $response = $this->bitlyClient->shorten(['long_url' => $this->dilbertClient->getUrl()]);
            return $response['link'];
        });
    }

    /**
     * Create twitter status
     *
     * @param string $mediaId
     * @param string $shortUrl
     * @return string
     * @throws FailedException
     */
    private function createTwitterStatus(string $mediaId, string $shortUrl): string
    {
        $executor = ExecutorFactory::make('image', $this->logger, new ExponentialBackoffStrategy());

        return $executor->execute(15, function () use ($mediaId, $shortUrl) {
            // create message
            $today = new DateTime();
            $message = sprintf('Dilbert comic for %s %s', $today->format('M jS, Y'), $shortUrl);

            return $this->twitterClient->createStatusWithImage($mediaId, $message);
        });
    }

    /**
     * Make logger
     *
     * @return LoggerInterface
     * @throws Exception
     */
    private function getLogger(): LoggerInterface
    {
        $logger = new Logger(self::LOG_NAME);
        $logger->pushHandler(new StreamHandler(__DIR__ . self::LOG_LOCATION));
        $logger->pushHandler(new NativeMailerHandler(self::LOG_EMAIL_TO, self::LOG_MESSAGE, self::LOG_EMAIL_FROM));

        return $logger;
    }

}
