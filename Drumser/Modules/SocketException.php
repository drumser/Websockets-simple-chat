<?php

namespace Drumser\Modules;

/**
 * Class SocketException
 * @package Drumser\Modules
 */
class SocketException extends \Exception
{
    /**
     * SocketException constructor.
     * @param string $message
     * @param int $code
     * @param \Exception|null $previous
     */
    public function __construct($message, $code = 0, \Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Generate last socket error for throwing
     * @param $socket_last_error
     * @return string
     */
    public static function generateMessage($socket_last_error) {
        return socket_strerror($socket_last_error);
    }

    /**
     * override __toString method
     * @return string
     */
    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}