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

use JosephOpanel\SolanaSDK\Endpoints\JsonRPC\Transaction;
use JosephOpanel\SolanaSDK\SolanaRPC;
use PHPUnit\Framework\TestCase;

class TransactionTest extends TestCase {
    public function testGetSignatureStatuses(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('getSignatureStatuses', [['signature1', 'signature2'], ['searchTransactionHistory' => true]])
            ->willReturn([
                [
                    'slot' => 123456,
                    'confirmations' => 10,
                    'status' => ['Ok' => null],
                    'err' => null,
                ],
                [
                    'slot' => 123457,
                    'confirmations' => null,
                    'status' => null,
                    'err' => ['InstructionError' => [0, 'Custom']],
                ],
            ]);

        $transaction = new Transaction($mockRpc);
        $result = $transaction->getSignatureStatuses(['signature1', 'signature2'], ['searchTransactionHistory' => true]);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals(123456, $result[0]['slot']);
        $this->assertEquals(10, $result[0]['confirmations']);
        $this->assertEquals(['Ok' => null], $result[0]['status']);
        $this->assertEquals(['InstructionError' => [0, 'Custom']], $result[1]['err']);
    }

    public function testGetSignaturesForAddress(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('getSignaturesForAddress', ['address1', ['limit' => 10, 'commitment' => 'finalized']])
            ->willReturn([
                [
                    'signature' => 'signature1',
                    'slot' => 123456,
                    'err' => null,
                    'memo' => 'Test memo',
                    'blockTime' => 1638336000,
                ],
                [
                    'signature' => 'signature2',
                    'slot' => 123457,
                    'err' => ['InstructionError' => [0, 'Custom']],
                    'memo' => null,
                    'blockTime' => 1638337000,
                ],
            ]);

        $transaction = new Transaction($mockRpc);
        $result = $transaction->getSignaturesForAddress('address1', ['limit' => 10, 'commitment' => 'finalized']);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('signature1', $result[0]['signature']);
        $this->assertEquals(123456, $result[0]['slot']);
        $this->assertEquals('Test memo', $result[0]['memo']);
        $this->assertEquals(['InstructionError' => [0, 'Custom']], $result[1]['err']);
    }

    public function testGetTransaction(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('getTransaction', ['signature123', ['commitment' => 'finalized', 'encoding' => 'json']])
            ->willReturn([
                'slot' => 123456,
                'transaction' => [
                    'message' => ['accountKeys' => ['key1', 'key2']],
                    'signatures' => ['signature123'],
                ],
                'meta' => [
                    'status' => ['Ok' => null],
                    'fee' => 5000,
                    'preBalances' => [1000000, 2000000],
                    'postBalances' => [995000, 2000000],
                ],
                'blockTime' => 1638337000,
            ]);

        $transaction = new Transaction($mockRpc);
        $result = $transaction->getTransaction('signature123', ['commitment' => 'finalized', 'encoding' => 'json']);

        $this->assertIsArray($result);
        $this->assertEquals(123456, $result['slot']);
        $this->assertEquals('key1', $result['transaction']['message']['accountKeys'][0]);
        $this->assertEquals(5000, $result['meta']['fee']);
        $this->assertEquals(1638337000, $result['blockTime']);
    }

    public function testSendTransaction(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('sendTransaction', ['signedTransactionBase64', ['skipPreflight' => false, 'preflightCommitment' => 'processed']])
            ->willReturn(['signature' => 'transactionSignature123']); // Mock response with 'signature'

        $transaction = new Transaction($mockRpc);
        $result = $transaction->sendTransaction('signedTransactionBase64', ['skipPreflight' => false, 'preflightCommitment' => 'processed']);

        $this->assertIsString($result);
        $this->assertEquals('transactionSignature123', $result);
    }

    public function testSimulateTransaction(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('simulateTransaction', ['signedTransactionBase64', ['sigVerify' => true, 'commitment' => 'processed']])
            ->willReturn([
                'value' => [
                    'err' => null,
                    'logs' => ['Instruction 1: success', 'Instruction 2: success'],
                    'unitsConsumed' => 12345,
                ],
            ]);

        $transaction = new Transaction($mockRpc);
        $result = $transaction->simulateTransaction('signedTransactionBase64', ['sigVerify' => true, 'commitment' => 'processed']);

        $this->assertIsArray($result);
        $this->assertNull($result['err']);
        $this->assertEquals(['Instruction 1: success', 'Instruction 2: success'], $result['logs']);
        $this->assertEquals(12345, $result['unitsConsumed']);
    }




}
