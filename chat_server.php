<?php

require_once "autoloader.php";

use Drumser\Modules\WebsocketChatServer;

$chat = new WebsocketChatServer('127.0.0.1', '8080', 10);
$chat->process();