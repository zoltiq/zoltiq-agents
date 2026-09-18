<?php

namespace JosephOpanel\SolanaSDK\WebSocket;

use Ratchet\Client\WebSocket;
use Ratchet\Client\Connector;
use React\EventLoop\Factory;
use React\Promise\Deferred;

class WebSocketClient
{
    private $uri;
    private $loop;
    private $connector;

    public function __construct(string $uri = 'wss://api.mainnet-beta.solana.com/')
    {
        $this->uri = $uri;
        $this->loop = Factory::create();
        $this->connector = new Connector($this->loop);
    }

    public function connect(): \React\Promise\PromiseInterface
    {
        $deferred = new Deferred();

        // Use the connector property correctly
        $this->connector->__invoke($this->uri)->then(
            function (WebSocket $conn) use ($deferred) {
                $deferred->resolve($conn);
            },
            function (\Exception $e) use ($deferred) {
                $deferred->reject($e);
            }
        );

        $this->loop->run();

        return $deferred->promise();
    }
}
