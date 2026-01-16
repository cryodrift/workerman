#!/usr/bin/env php
<?php

namespace gfw;

use cryodrift\fw\Core;
use cryodrift\workerman\Handler;
use Workerman\Worker;
use cryodrift\fw\Config;
use cryodrift\fw\Main;
use Phar;

ob_start();
$loadmain = false;
$loadauto = false;
$rootdir = __DIR__;
// get autoload from upto 5 levels deep
for ($a = 1; $a <= 5; $a++) {
    $dir = dirname(__DIR__, $a);
    $pathname = $dir . '/vendor/autoload.php';
    if (file_exists($pathname)) {
        require_once $pathname;
        $loadauto = true;
    }
    $pathname = $dir . '/sys/Main.php';
    if (file_exists($pathname)) {
        require_once $pathname;
        $loadmain = true;
        $rootdir = $dir;
    }
}

//TODO can we really run this from inside a phar?
Main::$rootdir = dirname(Phar::running(false)) ? dirname(Phar::running(false)) . '/' : $rootdir . '/';
//NON COMPOSER MODE
if ($loadmain) {
    Main::autoload('cryodrift', Main::$rootdir . 'src');
    Main::autoload('cryodrift\\fw', Main::$rootdir . 'sys');
    Main::autoload('Protocols', Main::$rootdir . 'vendor/workerman/workerman/src/Protocols');
    Main::autoloader();
    Config::$datadir = Main::$rootdir . '.data/';
    Config::$logdir = '';
    Config::$includedirs = [
            Main::$rootdir . 'src/',
            Main::$rootdir . 'sys/',
            Main::$rootdir
    ];
} else {
    //allow overrides
    Config::$includedirs = [
            '.',
            './',
            Main::$rootdir . 'src/',
            Main::$rootdir . 'sys/',
            Main::$rootdir,
            Main::$rootdir . 'vendor/cryodrift/fw/',
            Main::$rootdir . 'vendor/cryodrift/'
    ];
}

// Define runtime constants
if (!defined('G_PHARFILE')) {
    define('G_PHARFILE', basename(Phar::running()));
}
if (!defined('G_PHAR')) {
    define('G_PHAR', 'phar://' . G_PHARFILE . '/');
}
if (!defined('G_PHARROOT')) {
    define('G_PHARROOT', dirname(__DIR__, 4));
}

set_include_path(implode(PATH_SEPARATOR, Config::$includedirs));
Worker::$logFile = Config::$datadir . Config::$logdir . 'workerman.log';
Core::fileWrite(Config::$datadir . Config::$logdir . 'workerman.log', ob_get_clean(), FILE_APPEND);
// Create a Worker to listen on port 2345 and communicate using the http protocol
$worker = new Worker("http://0.0.0.0:2345");

$worker->count = 4;

$worker->onWorkerStart = fn(Worker $w) => new Handler()->onWorkerStart($w);

Worker::runAll();
