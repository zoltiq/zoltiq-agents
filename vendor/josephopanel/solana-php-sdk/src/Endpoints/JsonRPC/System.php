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

class System {
    private $rpc;

    public function __construct(SolanaRPC $rpc) {
        $this->rpc = $rpc;
    }

    public function getClusterNodes(): array {
        return $this->rpc->call('getClusterNodes', []);
    }

    public function getEpochInfo(string $commitment = 'finalized'): array {
        return $this->rpc->call('getEpochInfo', [['commitment' => $commitment]]);
    }

    public function getEpochSchedule(): array {
        return $this->rpc->call('getEpochSchedule', []);
    }

    public function getFeeForMessage(string $message, string $commitment = 'finalized'): ?int {
        $response = $this->rpc->call('getFeeForMessage', [['message' => $message, 'commitment' => $commitment]]);
        return $response['value'] ?? null; // Return fee or null if not available
    }

    public function getHealth(): string {
        $response = $this->rpc->call('getHealth', []);
        // Extract the 'status' key or consider the node unhealthy
        return $response['status'] ?? 'unhealthy';
    }

    public function getHighestSnapshotSlot(): array {
        return $this->rpc->call('getHighestSnapshotSlot', []);
    }

    public function getIdentity(): string {
        $response = $this->rpc->call('getIdentity', []);
        return $response['identity'] ?? '';
    }

    public function getInflationGovernor(string $commitment = 'finalized'): array {
        return $this->rpc->call('getInflationGovernor', [['commitment' => $commitment]]);
    }

    public function getInflationRate(): array {
        return $this->rpc->call('getInflationRate', []);
    }

    public function getInflationReward(array $addresses, ?int $epoch = null): array {
        $params = [$addresses];
        if ($epoch !== null) {
            $params[] = ['epoch' => $epoch];
        }
        return $this->rpc->call('getInflationReward', $params);
    }

    public function getLargestAccounts(string $filter = '', string $commitment = 'finalized'): array {
        $params = [['commitment' => $commitment]];
        if (!empty($filter)) {
            $params[0]['filter'] = $filter;
        }
        return $this->rpc->call('getLargestAccounts', $params);
    }

    public function getLeaderSchedule(?int $slot = null, string $commitment = 'finalized'): array {
        $params = [];
        if ($slot !== null) {
            $params[] = $slot;
        }
        $params[] = ['commitment' => $commitment];

        return $this->rpc->call('getLeaderSchedule', $params);
    }

    public function getMaxRetransmitSlot(): int {
        $response = $this->rpc->call('getMaxRetransmitSlot', []);
        return $response['slot'] ?? 0; // Safely extract the 'slot' key or default to 0
    }

    public function getMaxShredInsertSlot(): int {
        $response = $this->rpc->call('getMaxShredInsertSlot', []);
        return $response['slot'] ?? 0; // Safely extract the 'slot' key or default to 0
    }

    public function getMinimumBalanceForRentExemption(int $dataLength): int {
        $response = $this->rpc->call('getMinimumBalanceForRentExemption', [$dataLength]);
        return $response['lamports'] ?? 0; // Safely extract the 'lamports' key or default to 0
    }

    public function getRecentPerformanceSamples(?int $limit = null): array {
        $params = [];
        if ($limit !== null) {
            $params[] = $limit;
        }
        return $this->rpc->call('getRecentPerformanceSamples', $params);
    }

    public function getRecentPrioritizationFees(): array {
        return $this->rpc->call('getRecentPrioritizationFees', []);
    }

    public function getSlot(string $commitment = 'finalized'): int {
        $response = $this->rpc->call('getSlot', [['commitment' => $commitment]]);
        return $response['slot'] ?? 0; // Safely extract the 'slot' key or default to 0
    }

    public function getSlotLeader(string $commitment = 'finalized'): string {
        $response = $this->rpc->call('getSlotLeader', [['commitment' => $commitment]]);
        return $response['leader'] ?? ''; // Safely extract the 'leader' key or default to an empty string
    }

    public function getSlotLeaders(int $startSlot, int $limit): array {
        return $this->rpc->call('getSlotLeaders', [$startSlot, $limit]);
    }

    public function getStakeMinimumDelegation(): int {
        $response = $this->rpc->call('getStakeMinimumDelegation', []);
        return $response['lamports'] ?? 0; // Safely extract the 'lamports' key or default to 0
    }

    public function getSupply(string $commitment = 'finalized'): array {
        $response = $this->rpc->call('getSupply', [['commitment' => $commitment]]);
        return $response ?? []; // Return the response or an empty array
    }

    public function getTransactionCount(string $commitment = 'finalized'): int {
        $response = $this->rpc->call('getTransactionCount', [['commitment' => $commitment]]);
        return $response['count'] ?? 0; // Safely extract the 'count' key or default to 0
    }

    public function getVersion(): array {
        return $this->rpc->call('getVersion', []);
    }

    public function getVoteAccounts(string $commitment = 'finalized'): array {
        return $this->rpc->call('getVoteAccounts', [['commitment' => $commitment]]);
    }

    public function requestAirdrop(string $address, int $lamports, string $commitment = 'finalized'): string {
        $response = $this->rpc->call('requestAirdrop', [$address, $lamports, ['commitment' => $commitment]]);
        return $response['signature'] ?? ''; // Safely extract the 'signature' key or default to an empty string
    }







}
