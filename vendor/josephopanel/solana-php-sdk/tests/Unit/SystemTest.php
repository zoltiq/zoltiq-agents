<?php

/**
 * This file is part of the Solana PHP SDK.
 *
 * (c) Joseph Opanel <opanelj@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use JosephOpanel\SolanaSDK\SolanaRPC;
use JosephOpanel\SolanaSDK\Endpoints\JsonRPC\System;

class SystemTest extends TestCase {
    public function testGetClusterNodes(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('getClusterNodes', [])
            ->willReturn([
                [
                    'pubkey' => 'node1pubkey',
                    'gossip' => '127.0.0.1:8001',
                    'tpu' => '127.0.0.1:8002',
                    'rpc' => '127.0.0.1:8003',
                    'version' => '1.8.0',
                ],
                [
                    'pubkey' => 'node2pubkey',
                    'gossip' => '127.0.0.2:8001',
                    'tpu' => '127.0.0.2:8002',
                    'rpc' => null,
                    'version' => '1.8.1',
                ],
            ]);

        $system = new System($mockRpc);
        $result = $system->getClusterNodes();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('node1pubkey', $result[0]['pubkey']);
        $this->assertEquals('1.8.1', $result[1]['version']);
    }

    public function testGetEpochInfo(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('getEpochInfo', [['commitment' => 'finalized']])
            ->willReturn([
                'epoch' => 200,
                'slotIndex' => 400,
                'slotsInEpoch' => 432000,
                'absoluteSlot' => 12345678,
                'blockHeight' => 2345678,
                'transactionCount' => 9876543,
            ]);

        $system = new System($mockRpc);
        $result = $system->getEpochInfo();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('epoch', $result);
        $this->assertEquals(200, $result['epoch']);
        $this->assertArrayHasKey('blockHeight', $result);
        $this->assertEquals(2345678, $result['blockHeight']);
    }

    public function testGetEpochSchedule(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('getEpochSchedule', [])
            ->willReturn([
                'slotsPerEpoch' => 432000,
                'leaderScheduleSlotOffset' => 43200,
                'warmup' => true,
                'firstNormalEpoch' => 200,
                'firstNormalSlot' => 123456,
            ]);

        $system = new System($mockRpc);
        $result = $system->getEpochSchedule();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('slotsPerEpoch', $result);
        $this->assertEquals(432000, $result['slotsPerEpoch']);
        $this->assertArrayHasKey('warmup', $result);
        $this->assertTrue($result['warmup']);
    }

    public function testGetFeeForMessage(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('getFeeForMessage', [['message' => 'base64Message', 'commitment' => 'finalized']])
            ->willReturn(['value' => 5000]);

        $system = new System($mockRpc);
        $result = $system->getFeeForMessage('base64Message');

        $this->assertIsInt($result);
        $this->assertEquals(5000, $result);
    }

    public function testGetHealth(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        // Case 1: Healthy node response
        $mockRpc->expects($this->exactly(1)) // Ensure this mock is called once
            ->method('call')
            ->with('getHealth', [])
            ->willReturn(['status' => 'ok']); // Healthy node response

        $system = new System($mockRpc);
        $result = $system->getHealth();
        $this->assertEquals('ok', $result);

        // Case 2: Unhealthy node response
        $mockRpc = $this->createMock(SolanaRPC::class); // New mock for second scenario
        $mockRpc->expects($this->exactly(1)) // Ensure this mock is called once
            ->method('call')
            ->with('getHealth', [])
            ->willReturn([]); // No 'status', implying unhealthy

        $system = new System($mockRpc);
        $result = $system->getHealth();
        $this->assertEquals('unhealthy', $result);
    }

    public function testGetHighestSnapshotSlot(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('getHighestSnapshotSlot', [])
            ->willReturn([
                'full' => 123456,
                'incremental' => 123460,
            ]);

        $system = new System($mockRpc);
        $result = $system->getHighestSnapshotSlot();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('full', $result);
        $this->assertArrayHasKey('incremental', $result);
        $this->assertEquals(123456, $result['full']);
        $this->assertEquals(123460, $result['incremental']);
    }

    public function testGetIdentity(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);

        $mockRpc->method('call')
            ->with('getIdentity', [])
            ->willReturn([
                'identity' => 'validatorPublicKey123456'
            ]);

        $system = new System($mockRpc);
        $result = $system->getIdentity();

        $this->assertIsString($result);
        $this->assertEquals('validatorPublicKey123456', $result);
    }

    public function testGetInflationGovernor(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('getInflationGovernor', [['commitment' => 'finalized']])
            ->willReturn([
                'foundation' => 0.05,
                'foundationTerm' => 2.0,
                'initial' => 0.15,
                'taper' => 0.15,
                'terminal' => 0.015,
            ]);

        $system = new System($mockRpc);
        $result = $system->getInflationGovernor();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('foundation', $result);
        $this->assertEquals(0.05, $result['foundation']);
        $this->assertArrayHasKey('terminal', $result);
        $this->assertEquals(0.015, $result['terminal']);
    }

    public function testGetInflationRate(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);

        $mockRpc->method('call')
            ->with('getInflationRate', [])
            ->willReturn([
                'total' => 0.08,
                'validator' => 0.07,
                'foundation' => 0.01,
                'epoch' => 150,
            ]);

        $system = new System($mockRpc);
        $result = $system->getInflationRate();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total', $result);
        $this->assertEquals(0.08, $result['total']);
        $this->assertArrayHasKey('epoch', $result);
        $this->assertEquals(150, $result['epoch']);
    }

    public function testGetInflationReward(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('getInflationReward', [['address1', 'address2'], ['epoch' => 150]])
            ->willReturn([
                [
                    'epoch' => 150,
                    'amount' => 100000,
                    'postBalance' => 2000000,
                    'commission' => 5,
                ],
                [
                    'epoch' => 150,
                    'amount' => 50000,
                    'postBalance' => 1000000,
                    'commission' => 10,
                ],
            ]);

        $system = new System($mockRpc);
        $result = $system->getInflationReward(['address1', 'address2'], 150);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals(100000, $result[0]['amount']);
        $this->assertEquals(10, $result[1]['commission']);
    }

    public function testGetLargestAccounts(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('getLargestAccounts', [['commitment' => 'finalized', 'filter' => 'circulating']])
            ->willReturn([
                [
                    'address' => 'address1',
                    'lamports' => 5000000000,
                ],
                [
                    'address' => 'address2',
                    'lamports' => 3000000000,
                ],
            ]);

        $system = new System($mockRpc);
        $result = $system->getLargestAccounts('circulating');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('address1', $result[0]['address']);
        $this->assertEquals(5000000000, $result[0]['lamports']);
    }

    public function testGetLeaderSchedule(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('getLeaderSchedule', [1000, ['commitment' => 'finalized']])
            ->willReturn([
                'validator1Pubkey' => [1000, 1001, 1002],
                'validator2Pubkey' => [1003, 1004],
            ]);

        $system = new System($mockRpc);
        $result = $system->getLeaderSchedule(1000);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('validator1Pubkey', $result);
        $this->assertEquals([1000, 1001, 1002], $result['validator1Pubkey']);
    }

    public function testGetMaxRetransmitSlot(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('getMaxRetransmitSlot', [])
            ->willReturn(['slot' => 123456]); // Mock response with 'slot' key

        $system = new System($mockRpc);
        $result = $system->getMaxRetransmitSlot();

        $this->assertIsInt($result);
        $this->assertEquals(123456, $result);
    }

    public function testGetMaxShredInsertSlot(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('getMaxShredInsertSlot', [])
            ->willReturn(['slot' => 654321]); // Mock response with 'slot' key

        $system = new System($mockRpc);
        $result = $system->getMaxShredInsertSlot();

        $this->assertIsInt($result);
        $this->assertEquals(654321, $result);
    }

    public function testGetMinimumBalanceForRentExemption(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('getMinimumBalanceForRentExemption', [128]) // Example: 128 bytes of data
            ->willReturn(['lamports' => 2039280]); // Mock response with 'lamports' key

        $system = new System($mockRpc);
        $result = $system->getMinimumBalanceForRentExemption(128);

        $this->assertIsInt($result);
        $this->assertEquals(2039280, $result);
    }

    public function testGetRecentPerformanceSamples(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('getRecentPerformanceSamples', [5]) // Example: Limit to 5 samples
            ->willReturn([
                [
                    'slot' => 123456,
                    'numTransactions' => 1000,
                    'numSlots' => 200,
                    'samplePeriodSecs' => 60,
                ],
                [
                    'slot' => 123457,
                    'numTransactions' => 800,
                    'numSlots' => 180,
                    'samplePeriodSecs' => 60,
                ],
            ]);

        $system = new System($mockRpc);
        $result = $system->getRecentPerformanceSamples(5);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals(123456, $result[0]['slot']);
        $this->assertEquals(1000, $result[0]['numTransactions']);
        $this->assertEquals(180, $result[1]['numSlots']);
    }

    public function testGetRecentPrioritizationFees(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('getRecentPrioritizationFees', [])
            ->willReturn([
                [
                    'slot' => 123456,
                    'prioritizationFee' => 500,
                ],
                [
                    'slot' => 123457,
                    'prioritizationFee' => 450,
                ],
            ]);

        $system = new System($mockRpc);
        $result = $system->getRecentPrioritizationFees();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals(123456, $result[0]['slot']);
        $this->assertEquals(500, $result[0]['prioritizationFee']);
        $this->assertEquals(450, $result[1]['prioritizationFee']);
    }

    public function testGetSlot(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('getSlot', [['commitment' => 'finalized']])
            ->willReturn(['slot' => 123456]); // Mock response with 'slot' key

        $system = new System($mockRpc);
        $result = $system->getSlot();

        $this->assertIsInt($result);
        $this->assertEquals(123456, $result);
    }

    public function testGetSlotLeader(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('getSlotLeader', [['commitment' => 'finalized']])
            ->willReturn(['leader' => 'leaderPublicKey123']); // Mock response with 'leader' key

        $system = new System($mockRpc);
        $result = $system->getSlotLeader();

        $this->assertIsString($result);
        $this->assertEquals('leaderPublicKey123', $result);
    }

    public function testGetSlotLeaders(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('getSlotLeaders', [123456, 10]) // Example: Start at slot 123456, retrieve 10 leaders
            ->willReturn([
                'leaderPublicKey1',
                'leaderPublicKey2',
                'leaderPublicKey3',
                'leaderPublicKey4',
                'leaderPublicKey5',
                'leaderPublicKey6',
                'leaderPublicKey7',
                'leaderPublicKey8',
                'leaderPublicKey9',
                'leaderPublicKey10',
            ]);

        $system = new System($mockRpc);
        $result = $system->getSlotLeaders(123456, 10);

        $this->assertIsArray($result);
        $this->assertCount(10, $result);
        $this->assertEquals('leaderPublicKey1', $result[0]);
        $this->assertEquals('leaderPublicKey10', $result[9]);
    }

    public function testGetStakeMinimumDelegation(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('getStakeMinimumDelegation', [])
            ->willReturn(['lamports' => 2039280]); // Mock response with 'lamports' key

        $system = new System($mockRpc);
        $result = $system->getStakeMinimumDelegation();

        $this->assertIsInt($result);
        $this->assertEquals(2039280, $result);
    }

    public function testGetSupply(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('getSupply', [['commitment' => 'finalized']])
            ->willReturn([
                'total' => 500000000000,
                'circulating' => 400000000000,
                'nonCirculating' => 100000000000,
                'nonCirculatingAccounts' => ['account1', 'account2'],
            ]);

        $system = new System($mockRpc);
        $result = $system->getSupply();

        $this->assertIsArray($result);
        $this->assertEquals(500000000000, $result['total']);
        $this->assertEquals(400000000000, $result['circulating']);
        $this->assertEquals(100000000000, $result['nonCirculating']);
        $this->assertCount(2, $result['nonCirculatingAccounts']);
    }

    public function testGetTransactionCount(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('getTransactionCount', [['commitment' => 'finalized']])
            ->willReturn(['count' => 12345678]); // Mock response with 'count' key

        $system = new System($mockRpc);
        $result = $system->getTransactionCount();

        $this->assertIsInt($result);
        $this->assertEquals(12345678, $result);
    }

    public function testGetVersion(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('getVersion', [])
            ->willReturn([
                'solana-core' => '1.9.28',
                'feature-set' => 123456789,
            ]); // Mock response with version details

        $system = new System($mockRpc);
        $result = $system->getVersion();

        $this->assertIsArray($result);
        $this->assertEquals('1.9.28', $result['solana-core']);
        $this->assertEquals(123456789, $result['feature-set']);
    }

    public function testGetVoteAccounts(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('getVoteAccounts', [['commitment' => 'finalized']])
            ->willReturn([
                'current' => [
                    [
                        'votePubkey' => 'voteAccount1',
                        'nodePubkey' => 'nodeAccount1',
                        'activatedStake' => 1000000,
                        'epochCredits' => [100, 200, 300],
                        'commission' => 5,
                    ],
                ],
                'delinquent' => [
                    [
                        'votePubkey' => 'voteAccount2',
                        'nodePubkey' => 'nodeAccount2',
                        'activatedStake' => 500000,
                        'epochCredits' => [50, 100],
                        'commission' => 10,
                    ],
                ],
            ]);

        $system = new System($mockRpc);
        $result = $system->getVoteAccounts();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('current', $result);
        $this->assertArrayHasKey('delinquent', $result);
        $this->assertEquals('voteAccount1', $result['current'][0]['votePubkey']);
        $this->assertEquals(10, $result['delinquent'][0]['commission']);
    }

    public function testRequestAirdrop(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('requestAirdrop', ['recipientAddress', 1000000, ['commitment' => 'finalized']])
            ->willReturn(['signature' => 'transactionSignature123']); // Mock response with 'signature'

        $system = new System($mockRpc);
        $result = $system->requestAirdrop('recipientAddress', 1000000, 'finalized');

        $this->assertIsString($result);
        $this->assertEquals('transactionSignature123', $result);
    }







}
