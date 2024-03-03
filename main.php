<?php

error_reporting(E_ALL);

/**
 * load composer packages
 */
require_once 'vendor/autoload.php';

/**
 * import handler class
 */
use TeleBot\System\BotHandler;
use TeleBot\System\SessionManager;

/**
 * create and run instance
 */
$h = new BotHandler();
$h->start();
