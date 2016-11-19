<?php

namespace Drumser\Modules;

/**
 * Class Websocket
 * @package Drumser\Modules
 */
class Websocket
{
    /**
     * IP addres of socket
     * @example 127.0.0.1
     * @var string
     */
    private $addr;
    /**
     * Port of socket
     * @example 8080
     * @var string
     */
    private $port;
    /**
     * Max connections for listening in queue
     * @var
     */
    private $max_connections;

    /**
     * Main socket resource
     * @var resource
     */
    private $sock;

    /**
     * Websocket constructor.
     * @param string $addr
     * @param string $port
     * @param int $max_connections
     * @throws \Drumser\Modules\SocketException
     * @return \Drumser\Modules\Websocket
     */
    public function __construct($addr="127.0.0.1", $port="8080", $max_connections=10)
    {

        $this->addr = $addr;
        $this->port = $port;


        $this->sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$this->sock) {
            throw new SocketException("socket_create() error: " . SocketException::generateMessage(socket_last_error()) );
        }
        echo "Socket created\n";

        $this->setSocketOpts();
        echo "Socket ops setted\n";

        $this->bindAndListen();

        return $this->sock;
    }

    /**
     * Procudure which bind and listen socket
     * @return bool
     * @throws \Drumser\Modules\SocketException
     */
    public function bindAndListen() {
        $sb = socket_bind($this->sock, $this->addr, $this->port);
        if (!$sb) {
            throw new SocketException("socket_bind() error: " . SocketException::generateMessage(socket_last_error()) );
        }
        echo "Socket binded at $this->addr:$this->port\n";


        $sl = socket_listen($this->sock, $this->max_connections);
        if (!$sl) {
            throw new SocketException("socket_listen() error: " . SocketException::generateMessage(socket_last_error()) );
        }
        echo "Socket listened\n";

        return true;
    }

    /**
     * Set options for reusable and non-blocking sockets
     */
    public function setSocketOpts() {
        socket_set_nonblock($this->sock);
        socket_set_option($this->sock, SOL_SOCKET, SO_REUSEADDR, 1);
    }

    /**
     * @return string
     */
    public function getAddr()
    {
        return $this->addr;
    }

    /**
     * @return string
     */
    public function getPort()
    {
        return $this->port;
    }


    /**
     * Make handshake with browser and server
     * @param $client
     * @param $headers
     * @param $socket
     * @return bool
     */
    public function handshake($client, $headers, $socket) {
        if(preg_match("/Sec-WebSocket-Version: (.*)\r\n/", $headers, $match))
            $version = $match[1];
        else {
            echo ("The client doesn't support WebSocket\n");
            return false;
        }
        if( $version == 13 ) {
            // Extract header variables
            if(preg_match("/GET (.*) HTTP/", $headers, $match))
                $root = $match[1];
            if(preg_match("/Host: (.*)\r\n/", $headers, $match))
                $host = $match[1];
            if(preg_match("/Origin: (.*)\r\n/", $headers, $match))
                $origin = $match[1];
            if(preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $headers, $match))
                $key = $match[1];

            $acceptKey = $key.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
            $acceptKey = base64_encode(sha1($acceptKey, true));

            $upgrade = "HTTP/1.1 101 Switching Protocols\r\n".
                "Upgrade: websocket\r\n".
                "Connection: Upgrade\r\n".
                "Sec-WebSocket-Accept: $acceptKey".
                "\r\n\r\n";

            socket_write($client, $upgrade);
            return true;
        }
        else {
            echo ("WebSocket version 13 required (the client supports version {$version})\n");
            return false;
        }
    }


    /**
     * Encode message for send to browser
     * @param $payload
     * @param string $type
     * @param bool $masked
     * @return string
     */
    public function encode($payload, $type = 'text', $masked = false)
    {
        $frameHead = array();
        $payloadLength = strlen($payload);

        switch ($type) {
            case 'text':
                // first byte indicates FIN, Text-Frame (10000001):
                $frameHead[0] = 129;
                break;

            case 'close':
                // first byte indicates FIN, Close Frame(10001000):
                $frameHead[0] = 136;
                break;

            case 'ping':
                // first byte indicates FIN, Ping frame (10001001):
                $frameHead[0] = 137;
                break;

            case 'pong':
                // first byte indicates FIN, Pong frame (10001010):
                $frameHead[0] = 138;
                break;
        }

        // set mask and payload length (using 1, 3 or 9 bytes)
        if ($payloadLength > 65535) {
            $payloadLengthBin = str_split(sprintf('%064b', $payloadLength), 8);
            $frameHead[1] = ($masked === true) ? 255 : 127;
            for ($i = 0; $i < 8; $i++) {
                $frameHead[$i + 2] = bindec($payloadLengthBin[$i]);
            }
            // most significant bit MUST be 0
            if ($frameHead[2] > 127) {
                return array('type' => '', 'payload' => '', 'error' => 'frame too large (1004)');
            }
        } elseif ($payloadLength > 125) {
            $payloadLengthBin = str_split(sprintf('%016b', $payloadLength), 8);
            $frameHead[1] = ($masked === true) ? 254 : 126;
            $frameHead[2] = bindec($payloadLengthBin[0]);
            $frameHead[3] = bindec($payloadLengthBin[1]);
        } else {
            $frameHead[1] = ($masked === true) ? $payloadLength + 128 : $payloadLength;
        }

        // convert frame-head to string:
        foreach (array_keys($frameHead) as $i) {
            $frameHead[$i] = chr($frameHead[$i]);
        }
        if ($masked === true) {
            // generate a random mask:
            $mask = array();
            for ($i = 0; $i < 4; $i++) {
                $mask[$i] = chr(rand(0, 255));
            }

            $frameHead = array_merge($frameHead, $mask);
        }
        $frame = implode('', $frameHead);

        // append payload to frame:
        for ($i = 0; $i < $payloadLength; $i++) {
            $frame .= ($masked === true) ? $payload[$i] ^ $mask[$i % 4] : $payload[$i];
        }

        return $frame;
    }

    /**
     * Decode message from browser
     * @param $data
     * @return array|bool
     */
    public function decode($data)
    {
        $unmaskedPayload = '';
        $decodedData = array();

        // estimate frame type:
        $firstByteBinary = sprintf('%08b', ord($data[0]));
        $secondByteBinary = sprintf('%08b', ord($data[1]));
        $opcode = bindec(substr($firstByteBinary, 4, 4));
        $isMasked = ($secondByteBinary[0] == '1') ? true : false;
        $payloadLength = ord($data[1]) & 127;

        // unmasked frame is received:
        if (!$isMasked) {
            return array('type' => '', 'payload' => '', 'error' => 'protocol error (1002)');
        }

        switch ($opcode) {
            // text frame:
            case 1:
                $decodedData['type'] = 'text';
                break;

            case 2:
                $decodedData['type'] = 'binary';
                break;

            // connection close frame:
            case 8:
                $decodedData['type'] = 'close';
                break;

            // ping frame:
            case 9:
                $decodedData['type'] = 'ping';
                break;

            // pong frame:
            case 10:
                $decodedData['type'] = 'pong';
                break;

            default:
                return array('type' => '', 'payload' => '', 'error' => 'unknown opcode (1003)');
        }

        if ($payloadLength === 126) {
            $mask = substr($data, 4, 4);
            $payloadOffset = 8;
            $dataLength = bindec(sprintf('%08b', ord($data[2])) . sprintf('%08b', ord($data[3]))) + $payloadOffset;
        } elseif ($payloadLength === 127) {
            $mask = substr($data, 10, 4);
            $payloadOffset = 14;
            $tmp = '';
            for ($i = 0; $i < 8; $i++) {
                $tmp .= sprintf('%08b', ord($data[$i + 2]));
            }
            $dataLength = bindec($tmp) + $payloadOffset;
            unset($tmp);
        } else {
            $mask = substr($data, 2, 4);
            $payloadOffset = 6;
            $dataLength = $payloadLength + $payloadOffset;
        }

        /**
         * We have to check for large frames here. socket_recv cuts at 1024 bytes
         * so if websocket-frame is > 1024 bytes we have to wait until whole
         * data is transferd.
         */
        if (strlen($data) < $dataLength) {
            return false;
        }

        if ($isMasked) {
            for ($i = $payloadOffset; $i < $dataLength; $i++) {
                $j = $i - $payloadOffset;
                if (isset($data[$i])) {
                    $unmaskedPayload .= $data[$i] ^ $mask[$j % 4];
                }
            }
            $decodedData['payload'] = $unmaskedPayload;
        } else {
            $payloadOffset = $payloadOffset - 4;
            $decodedData['payload'] = substr($data, $payloadOffset);
        }

        return $decodedData;
    }

}