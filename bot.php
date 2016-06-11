#!/usr/bin/php
<?php

    require_once 'vendor/autoload.php';

    $bot = new \UploaderBot\UploaderBot();
    $bot
        ->loadConfig()
        ->init()
        ->run();