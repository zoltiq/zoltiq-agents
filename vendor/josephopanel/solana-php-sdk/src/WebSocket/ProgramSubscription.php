<?php

namespace JosephOpanel\SolanaSDK\WebSocket;

use React\Promise\PromiseInterface;

class ProgramSubscription extends WebSocketClient
{
    public function subscribe(
        string $programId,
        callable $callback,
        array $params = []
    ): PromiseInterface {
        return $this->connect()->then(function ($conn) use ($programId, $callback, $params) {
            $request = [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'programSubscribe',
                'params' => [$programId, $params]
            ];

            $conn->send(json_encode($request));

            $conn->on('message', function ($message) use ($callback) {
                $callback(json_decode($message, true));
            });

            return $conn;
        });
    }

    public function unsubscribe(int $subscriptionId): PromiseInterface
    {
        return $this->connect()->then(function ($conn) use ($subscriptionId) {
            $request = [
                'jsonrpc' => '2.0',
                'id' => 2,
                'method' => 'programUnsubscribe',
                'params' => [$subscriptionId]
            ];

            $conn->send(json_encode($request));
        });
    }
}
