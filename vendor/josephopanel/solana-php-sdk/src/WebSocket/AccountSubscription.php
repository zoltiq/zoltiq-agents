<?php

namespace JosephOpanel\SolanaSDK\WebSocket;

use React\Promise\PromiseInterface;

class AccountSubscription extends WebSocketClient
{
    public function subscribe(string $account, callable $callback): PromiseInterface
    {
        return $this->connect()->then(function ($conn) use ($account, $callback) {
            $request = [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'accountSubscribe',
                'params' => [$account, ['commitment' => 'finalized']]
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
                'method' => 'accountUnsubscribe',
                'params' => [$subscriptionId]
            ];

            $conn->send(json_encode($request));
        });
    }
}
