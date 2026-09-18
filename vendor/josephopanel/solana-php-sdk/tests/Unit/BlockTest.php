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
use JosephOpanel\SolanaSDK\Endpoints\JsonRPC\Block;

class BlockTest extends TestCase {
    public function testGetBlock(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('getBlock', [123456789, []])
            ->willReturn([
                'blockHeight' => 1000,
                'transactions' => [],
                'rewards' => []
            ]);

        $block = new Block($mockRpc);
        $result = $block->getBlock(123456789);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('blockHeight', $result);
        $this->assertEquals(1000, $result['blockHeight']);
    }

    public function testGetBlockCommitment(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('getBlockCommitment', [123456789])
            ->willReturn([
                'commitment' => [50, 30, 20],
                'totalStake' => 100
            ]);

        $block = new Block($mockRpc);
        $result = $block->getBlockCommitment(123456789);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('commitment', $result);
        $this->assertArrayHasKey('totalStake', $result);
        $this->assertEquals(100, $result['totalStake']);
    }

    public function testGetBlockHeight(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('getBlockHeight', [['commitment' => 'finalized']])
            ->willReturn(['blockHeight' => 123456]); // Return an array with the expected key

        $block = new Block($mockRpc);
        $result = $block->getBlockHeight();

        $this->assertIsInt($result);
        $this->assertEquals(123456, $result);
    }

    public function testGetBlockProduction(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('getBlockProduction', [[]])
            ->willReturn([
                'byIdentity' => [
                    'validator1' => [10, 2],
                    'validator2' => [20, 5],
                ],
                'range' => [
                    'firstSlot' => 1000,
                    'lastSlot' => 1100,
                ]
            ]);

        $block = new Block($mockRpc);
        $result = $block->getBlockProduction();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('byIdentity', $result);
        $this->assertArrayHasKey('range', $result);
        $this->assertEquals(1000, $result['range']['firstSlot']);
        $this->assertEquals(1100, $result['range']['lastSlot']);
    }

    public function testGetBlockTime(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('getBlockTime', [123456])
            ->willReturn(['blockTime' => 1638579273]); // Mock response with blockTime key

        $block = new Block($mockRpc);
        $result = $block->getBlockTime(123456);

        $this->assertIsInt($result);
        $this->assertEquals(1638579273, $result);
    }

    public function testGetBlocks(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('getBlocks', [1000, 1100])
            ->willReturn([1000, 1001, 1002, 1003, 1100]);

        $block = new Block($mockRpc);
        $result = $block->getBlocks(1000, 1100);

        $this->assertIsArray($result);
        $this->assertCount(5, $result);
        $this->assertEquals(1000, $result[0]);
        $this->assertEquals(1100, $result[4]);
    }

    public function testGetFirstAvailableBlock(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('getFirstAvailableBlock', [])
            ->willReturn(['block' => 100]);

        $block = new Block($mockRpc);
        $result = $block->getFirstAvailableBlock();

        $this->assertIsInt($result);
        $this->assertEquals(100, $result);
    }

    public function testGetGenesisHash(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('getGenesisHash', [])
            ->willReturn(['hash' => '5eykt4UsFv8P8NJdTREpGZ5zPPz6fHpT1vRoDfg1baxM']); // Mock response with hash key

        $block = new Block($mockRpc);
        $result = $block->getGenesisHash();

        $this->assertIsString($result);
        $this->assertEquals('5eykt4UsFv8P8NJdTREpGZ5zPPz6fHpT1vRoDfg1baxM', $result);
    }

    public function testGetLatestBlockhash(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('getLatestBlockhash', [['commitment' => 'finalized']])
            ->willReturn([
                'blockhash' => 'abcd1234blockhash',
                'lastValidBlockHeight' => 123456,
            ]);

        $block = new Block($mockRpc);
        $result = $block->getLatestBlockhash();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('blockhash', $result);
        $this->assertEquals('abcd1234blockhash', $result['blockhash']);
        $this->assertArrayHasKey('lastValidBlockHeight', $result);
        $this->assertEquals(123456, $result['lastValidBlockHeight']);
    }

    public function testIsBlockhashValid(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('isBlockhashValid', ['blockhash123', ['commitment' => 'finalized']])
            ->willReturn(['valid' => true]); // Mock response with 'valid' key

        $block = new Block($mockRpc);
        $result = $block->isBlockhashValid('blockhash123', 'finalized');

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testMinimumLedgerSlot(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('minimumLedgerSlot', [])
            ->willReturn(['slot' => 12345]); // Mock response with 'slot' key

        $block = new Block($mockRpc);
        $result = $block->minimumLedgerSlot();

        $this->assertIsInt($result);
        $this->assertEquals(12345, $result);
    }






}
