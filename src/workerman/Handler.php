<?php

namespace cryodrift\workerman;

use cryodrift\fw\Config;
use cryodrift\fw\Core;
use cryodrift\fw\Main;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Workerman\Worker;

class Handler
{
    private Config $config;

    public function __construct()
    {
        $this->config = Main::readConfig('web');

        ini_set('session.use_cookies', 0);
        session_cache_limiter('');
    }

    public function onWorkerStart(Worker $worker): void
    {
        // Call class methods
        $worker->onConnect = array($this, 'onConnect');
        $worker->onMessage = array($this, 'onMessage');
        $worker->onClose = array($this, 'onClose');
        $worker->onWorkerStop = array($this, 'onWorkerStop');
    }

    public function onConnect(TcpConnection $connection)
    {
    }

    public function onMessage(TcpConnection $connection, Request $d): void
    {
        global $_REQUEST, $_COOKIE, $_SESSION;
        static $request_count;
        // Business processing omitted
        if(++$request_count > 1000) {
            // Exit the current process after handling 10000 requests, the master process will automatically restart a new process
            Worker::stopAll();
        }
//    Core::echo('.');
        if (session_status() === PHP_SESSION_ACTIVE) {
            Core::log('session aborted');
            session_abort();
        }
        // reset globals
        Core::$log = $_REQUEST = $_COOKIE = $_SESSION = [];
        // init routes
        $_SERVER['REQUEST_URI'] = $d->path();
        $_SERVER['REQUEST_METHOD'] = $d->method();
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_REQUEST = array_merge($d->get(), $d->post());
        $_COOKIE = $d->cookie();
        $resp = Main::run($this->config, false);
        switch (true) {
            case $resp === false:
                $connection->send(new Response(204));
                break;
            case  is_string($resp):
                if (strlen($resp) < 1) {
                    $connection->send(new Response(204));
                    return;
                } else {
                    $connection->send($resp);
                }
                break;
            case $resp instanceof \cryodrift\fw\Response:
                $headers = Core::iterate($resp->getHeaders(), function ($h) {
                    [$name, $value] = explode(': ', $h, 2);
                    if ($name !== 'Set-Cookie') {
                        return [$name, \cryodrift\fw\Response::cleanHeader($value)];
                    }
                }, true);
                $cookies = $resp->getCookies();
                if ($cookies) {
                    $headers['Set-Cookie'] = $cookies;
                }

                $status = 200;
                if (Core::value('location', $headers)) {
                    $status = 302;
                }
                $response = new Response($status, $headers, (string)$resp);
                $connection->send($response);
                break;
        }
    }

    public function onClose(TcpConnection $connection)
    {
    }

    public function onWorkerStop(Worker $worker)
    {
    }
}

