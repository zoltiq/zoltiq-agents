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

class Block {
    private $rpc;

    public function __construct(SolanaRPC $rpc) {
        $this->rpc = $rpc;
    }

    public function getBlock(int $slot, array $config = []): array {
        return $this->rpc->call('getBlock', [$slot, $config]);
    }

    public function getBlockCommitment(int $block): array {
        return $this->rpc->call('getBlockCommitment', [$block]);
    }

    public function getBlockHeight(string $commitment = 'finalized'): int {
        $response = $this->rpc->call('getBlockHeight', [['commitment' => $commitment]]);
        return $response['blockHeight']; // Extract the block height explicitly
    }

    public function getBlockProduction(array $config = []): array {
        return $this->rpc->call('getBlockProduction', [$config]);
    }

    public function getBlockTime(int $slot): ?int {
        $response = $this->rpc->call('getBlockTime', [$slot]);
        return $response['blockTime'] ?? null; // Return the block time or null if not present
    }


    public function getBlocks(int $startSlot, ?int $endSlot = null): array {
        $params = [$startSlot];
        if ($endSlot !== null) {
            $params[] = $endSlot;
        }
        return $this->rpc->call('getBlocks', $params);
    }

    public function getBlocksWithLimit(int $startSlot, int $limit): array {
        return $this->rpc->call('getBlocksWithLimit', [$startSlot, $limit]);
    }

    public function getFirstAvailableBlock(): int {
        $response = $this->rpc->call('getFirstAvailableBlock', []);
        return $response['block'] ?? 0; // Ensure the returned value is extracted safely
    }

    public function getGenesisHash(): string {
        $response = $this->rpc->call('getGenesisHash', []);
        return $response['hash'] ?? ''; // Extract the hash or return an empty string if not present
    }

    public function getLatestBlockhash(string $commitment = 'finalized'): array {
        return $this->rpc->call('getLatestBlockhash', [['commitment' => $commitment]]);
    }

    public function isBlockhashValid(string $blockhash, string $commitment = 'finalized'): bool {
        $response = $this->rpc->call('isBlockhashValid', [$blockhash, ['commitment' => $commitment]]);
        return $response['valid'] ?? false; // Safely extract the 'valid' key or default to false
    }

    public function minimumLedgerSlot(): int {
        $response = $this->rpc->call('minimumLedgerSlot', []);
        return $response['slot'] ?? 0; // Safely extract the 'slot' key or default to 0
    }




}
