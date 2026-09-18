<?php

/**
 * This file is part of the Solana PHP SDK.
 *
 * (c) Joseph Opanel <opanelj@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace JosephOpanel\SolanaSDK\Endpoints\JsonRPC;

use JosephOpanel\SolanaSDK\SolanaRPC;

class Transaction {
    private $rpc;

    public function __construct(SolanaRPC $rpc) {
        $this->rpc = $rpc;
    }

    public function getSignatureStatuses(array $signatures, array $config = []): array {
        $params = [$signatures];
        if (!empty($config)) {
            $params[] = $config;
        }
        return $this->rpc->call('getSignatureStatuses', $params);
    }

    public function getSignaturesForAddress(string $address, array $config = []): array {
        $params = [$address];
        if (!empty($config)) {
            $params[] = $config;
        }
        return $this->rpc->call('getSignaturesForAddress', $params);
    }

    public function getTransaction(string $signature, array $config = []): array {
        $params = [$signature];
        if (!empty($config)) {
            $params[] = $config;
        }
        return $this->rpc->call('getTransaction', $params);
    }

    public function sendTransaction(string $transaction, array $config = []): string {
        $params = [$transaction];
        if (!empty($config)) {
            $params[] = $config;
        }
        $response = $this->rpc->call('sendTransaction', $params);
        return $response['signature'] ?? ''; // Safely extract the 'signature' key or default to an empty string
    }

    public function simulateTransaction(string $transaction, array $config = []): array {
        $params = [$transaction];
        if (!empty($config)) {
            $params[] = $config;
        }
        $response = $this->rpc->call('simulateTransaction', $params);
        return $response['value'] ?? []; // Safely extract the 'value' key or return an empty array
    }




}
