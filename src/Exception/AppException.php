<?php
/**
 * File AppException.php 
 */

namespace Tebru\DilbertPics\Exception;

use Exception;
use Monolog\Logger;

/**
 * Class AppException
 *
 * @author Nate Brunette <n@tebru.net>
 */
class AppException extends Exception
{
    public function __construct($message = '', $code = Logger::ERROR, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
