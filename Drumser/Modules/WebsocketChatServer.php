<?php

namespace Drumser\Modules;

/**
 * Class WebsocketChatServer
 * @package Drumser\Modules
 */
class WebsocketChatServer extends Websocket
{
    /**
     * Clients
     * @var array
     */
    private $client_sockets = array();

    /**
     * Chat messages
     * @var array
     */
    private $clients_data = array();

    /**
     * Instance of socket_create()
     * @var Websocket
     */
    private $sock;


    /**
     * Headers from browser
     * @var array
     */
    private $info;


    /**
     * WebsocketChatServer constructor.
     * @param string $addr
     * @param string $port
     * @param int $max_con
     */
    public function __construct($addr, $port, $max_con)
    {
        $this->sock = parent::__construct($addr, $port, $max_con);
    }

    /**
     * Process chat application
     */
    public function process() {
        $read = array($this->sock);
        $NULL = NULL;
        $abort = false;

        while (!$abort) {
            $num_changed = socket_select($read, $NULL, $NULL, 0 ,10);

            if ($num_changed) {
                // If new connection
                if (in_array($this->sock, $read)) {
                    $new_client = socket_accept($this->sock);
                    $header = socket_read($new_client, 1024); //read data sent by the socket
                    $this->info = parent::handshake($new_client, $header, $this->sock);
                    $this->client_sockets[] = $new_client;
                }
                // Loop clients
                foreach ($this->client_sockets as $key => $client) {
                    // If new data from client
                    if (in_array($client, $read)) {
                        $input = socket_read($client, 1024);
                        if ($input === false) {
                            socket_shutdown($client);
                            unset($this->client_sockets[$key]);
                        } else {
                            $input = trim($input);
                            $data = parent::decode($input)['payload'];
                            $expl = explode("|", $data);
                            $this->clients_data[$expl[0]] = $expl[1];
                        }

                        if ($input == 'exit') {
                            socket_close($this->sock);
                            $abort = true;
                        }
                    }
                }
            }

            // If new messages
            if (!empty($this->clients_data)) {
                foreach ($this->client_sockets as $key => $client) {
                    foreach ($this->clients_data as $cl=>$data) {
                        if (socket_write($client, parent::encode($cl.": ".$data))) {
                            echo "Message $data has been sent from $cl\n";
                        }
                    }

                }
                // Blank sended chat messages
                $this->clients_data = array();
            }


            $read = $this->client_sockets;
            $read[] = $this->sock;
        }
    }
}