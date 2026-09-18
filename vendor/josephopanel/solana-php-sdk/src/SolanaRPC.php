<?php

/**
 * This file is part of the Solana PHP SDK.
 *
 * (c) Joseph Opanel <opanelj@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace JosephOpanel\SolanaSDK;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class SolanaRPC {
    private $rpcUrl;
    private $client;

    public function __construct($rpcUrl = 'https://api.mainnet-beta.solana.com') {
        $this->rpcUrl = $rpcUrl;
        $this->client = new Client(['base_uri' => $this->rpcUrl]);
    }

    public function call(string $method, array $params = []): array {
        $payload = [
            'jsonrpc' => '2.0',
            'id' => uniqid(),
            'method' => $method,
            'params' => $params,
        ];

        try {
            $response = $this->client->post('', [
                'json' => $payload,
            ]);
            $data = json_decode($response->getBody(), true);

            if (isset($data['error'])) {
                throw new \Exception("RPC Error: " . $data['error']['message']);
            }

            return $data['result'];
        } catch (RequestException $e) {
            throw new \Exception("HTTP Request Error: " . $e->getMessage());
        }
    }
}
