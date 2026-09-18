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
use JosephOpanel\SolanaSDK\Endpoints\JsonRPC\Account;

class AccountTest extends TestCase {
    public function testGetBalance(): void {
        $mockRpc = $this->createMock(SolanaRPC::class);
        $mockRpc->method('call')
            ->with('getBalance', ['4izNYzN7uQac8jBDcD7NmuCpS8PqvYiHVSLXF5bY9Zrg', ['commitment' => 'finalized']])
            ->willReturn(['lamports' => 1000000]);

        $account = new Account($mockRpc);
        $result = $account->getBalance('4izNYzN7uQac8jBDcD7NmuCpS8PqvYiHVSLXF5bY9Zrg');
        
        $this->assertIsArray($result);
        $this->assertEquals(1000000, $result['lamports']);
    }

    public function testGetMultipleAccounts(): void {
	    $mockRpc = $this->createMock(SolanaRPC::class);
	    $mockRpc->method('call')
	        ->with('getMultipleAccounts', [['address1', 'address2'], ['commitment' => 'finalized', 'encoding' => 'jsonParsed']])
	        ->willReturn([
	            [
	                'lamports' => 1000000,
	                'owner' => 'ownerPubkey1',
	                'data' => ['parsed' => ['key' => 'account1Data']],
	                'executable' => false,
	                'rentEpoch' => 150,
	            ],
	            [
	                'lamports' => 2000000,
	                'owner' => 'ownerPubkey2',
	                'data' => ['parsed' => ['key' => 'account2Data']],
	                'executable' => false,
	                'rentEpoch' => 151,
	            ],
	        ]);

	    $account = new Account($mockRpc);
	    $result = $account->getMultipleAccounts(['address1', 'address2'], ['commitment' => 'finalized', 'encoding' => 'jsonParsed']);

	    $this->assertIsArray($result);
	    $this->assertCount(2, $result);
	    $this->assertEquals(1000000, $result[0]['lamports']);
	    $this->assertEquals('ownerPubkey2', $result[1]['owner']);
	}

	public function testGetTokenAccountsByDelegate(): void {
	    $mockRpc = $this->createMock(SolanaRPC::class);
	    $mockRpc->method('call')
	        ->with('getTokenAccountsByDelegate', [
	            'delegatePubkey',
	            [
	                'programId' => 'TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA',
	                'filters' => [
	                    [
	                        'memcmp' => [
	                            'offset' => 0,
	                            'bytes' => 'someMintAddress',
	                        ],
	                    ],
	                ],
	            ],
	        ])
	        ->willReturn([
	            [
	                'pubkey' => 'account1',
	                'account' => [
	                    'lamports' => 1000,
	                    'owner' => 'ownerPubkey',
	                    'data' => ['parsed' => ['info' => 'accountData1']],
	                    'executable' => false,
	                    'rentEpoch' => 150,
	                ],
	            ],
	            [
	                'pubkey' => 'account2',
	                'account' => [
	                    'lamports' => 2000,
	                    'owner' => 'ownerPubkey',
	                    'data' => ['parsed' => ['info' => 'accountData2']],
	                    'executable' => false,
	                    'rentEpoch' => 151,
	                ],
	            ],
	        ]);

	    $account = new Account($mockRpc);
	    $result = $account->getTokenAccountsByDelegate('delegatePubkey', ['mint' => 'someMintAddress']);

	    $this->assertIsArray($result);
	    $this->assertCount(2, $result);
	    $this->assertEquals('account1', $result[0]['pubkey']);
	    $this->assertEquals('accountData2', $result[1]['account']['data']['parsed']['info']);
	}

	public function testGetTokenAccountsByOwner(): void {
	    $mockRpc = $this->createMock(SolanaRPC::class);
	    $mockRpc->method('call')
	        ->with('getTokenAccountsByOwner', [
	            'ownerPubkey',
	            ['mint' => 'mintPubkey'],
	            ['encoding' => 'jsonParsed'], // Add the encoding parameter
	        ])
	        ->willReturn([
	            'value' => [ // Updated to match the actual response structure
	                [
	                    'pubkey' => 'account1',
	                    'account' => [
	                        'lamports' => 1000,
	                        'owner' => 'ownerPubkey',
	                        'data' => [
	                            'parsed' => [
	                                'info' => 'accountData1',
	                            ],
	                        ],
	                        'executable' => false,
	                        'rentEpoch' => 150,
	                    ],
	                ],
	                [
	                    'pubkey' => 'account2',
	                    'account' => [
	                        'lamports' => 2000,
	                        'owner' => 'ownerPubkey',
	                        'data' => [
	                            'parsed' => [
	                                'info' => 'accountData2',
	                            ],
	                        ],
	                        'executable' => false,
	                        'rentEpoch' => 151,
	                    ],
	                ],
	            ],
	        ]);

	    $account = new Account($mockRpc);
	    $result = $account->getTokenAccountsByOwner('ownerPubkey', ['mint' => 'mintPubkey']);

	    // Assertions to verify the structure and content of the result
	    $this->assertIsArray($result['value']);
	    $this->assertCount(2, $result['value']);
	    $this->assertEquals('account1', $result['value'][0]['pubkey']);
	    $this->assertEquals('accountData2', $result['value'][1]['account']['data']['parsed']['info']);
	}


	public function testGetTokenLargestAccounts(): void {
	    $mockRpc = $this->createMock(SolanaRPC::class);
	    $mockRpc->method('call')
	        ->with('getTokenLargestAccounts', ['mintPubkey', ['commitment' => 'finalized']])
	        ->willReturn([
	            'value' => [
	                [
	                    'address' => 'account1',
	                    'amount' => '1000000000',
	                    'decimals' => 6,
	                ],
	                [
	                    'address' => 'account2',
	                    'amount' => '500000000',
	                    'decimals' => 6,
	                ],
	            ],
	        ]);

	    $account = new Account($mockRpc);
	    $result = $account->getTokenLargestAccounts('mintPubkey', 'finalized');

	    $this->assertIsArray($result);
	    $this->assertCount(2, $result);
	    $this->assertEquals('account1', $result[0]['address']);
	    $this->assertEquals('500000000', $result[1]['amount']);
	}

	public function testGetTokenSupply(): void {
	    $mockRpc = $this->createMock(SolanaRPC::class);
	    $mockRpc->method('call')
	        ->with('getTokenSupply', ['mintPubkey', ['commitment' => 'finalized']])
	        ->willReturn([
	            'value' => [
	                'amount' => '1000000000000',
	                'decimals' => 6,
	                'uiAmount' => 1000000.0,
	                'uiAmountString' => '1000000.0',
	            ],
	        ]);

	    $account = new Account($mockRpc);
	    $result = $account->getTokenSupply('mintPubkey', 'finalized');

	    $this->assertIsArray($result);
	    $this->assertEquals('1000000000000', $result['amount']);
	    $this->assertEquals(6, $result['decimals']);
	    $this->assertEquals(1000000.0, $result['uiAmount']);
	    $this->assertEquals('1000000.0', $result['uiAmountString']);
	}

	public function testGetAccountInfoWithValidBase58(): void {
	    $mockRpc = $this->createMock(SolanaRPC::class);

	    // Mock response for a valid Base58 public key
	    $mockRpc->method('call')
	        ->with('getAccountInfo', [
	            '4izNYzN7uQac8jBDcD7NmuCpS8PqvYiHVSLXF5bY9Zrg',
	            ['commitment' => 'processed', 'encoding' => 'base64']
	        ])
	        ->willReturn([
	            'context' => ['slot' => 12345678],
	            'value' => [
	                'lamports' => 5000000000,
	                'owner' => '11111111111111111111111111111111',
	                'data' => '',
	                'executable' => false,
	                'rentEpoch' => 300,
	            ],
	        ]);

	    $account = new Account($mockRpc);
	    $result = $account->getAccountInfo('4izNYzN7uQac8jBDcD7NmuCpS8PqvYiHVSLXF5bY9Zrg');

	    $this->assertIsArray($result);
	    $this->assertArrayHasKey('lamports', $result['value']);
	    $this->assertEquals(5000000000, $result['value']['lamports']);
	    $this->assertEquals('11111111111111111111111111111111', $result['value']['owner']);
	    $this->assertFalse($result['value']['executable']);
	    $this->assertEquals(300, $result['value']['rentEpoch']);
	}

	public function testGetAccountInfoWithValidHex(): void {
	    $mockRpc = $this->createMock(SolanaRPC::class);

	    // Mock response for a valid Hex public key
	    $mockRpc->method('call')
	        ->with('getAccountInfo', [
	            'abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdef',
	            ['commitment' => 'processed', 'encoding' => 'base64']
	        ])
	        ->willReturn([
	            'context' => ['slot' => 12345678],
	            'value' => [
	                'lamports' => 1000000000,
	                'owner' => 'TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA',
	                'data' => '',
	                'executable' => false,
	                'rentEpoch' => 150,
	            ],
	        ]);

	    $account = new Account($mockRpc);
	    $result = $account->getAccountInfo('abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdef');

	    $this->assertIsArray($result);
	    $this->assertArrayHasKey('lamports', $result['value']);
	    $this->assertEquals(1000000000, $result['value']['lamports']);
	    $this->assertEquals('TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA', $result['value']['owner']);
	    $this->assertFalse($result['value']['executable']);
	    $this->assertEquals(150, $result['value']['rentEpoch']);
	}



	public function testGetAccountInfoWithCustomParams(): void {
	    $mockRpc = $this->createMock(SolanaRPC::class);

	    // Mock response for custom parameters
	    $mockRpc->method('call')
	        ->with('getAccountInfo', [
	            '4izNYzN7uQac8jBDcD7NmuCpS8PqvYiHVSLXF5bY9Zrg',
	            ['commitment' => 'finalized', 'encoding' => 'jsonParsed']
	        ])
	        ->willReturn([
	            'context' => ['slot' => 12345678],
	            'value' => [
	                'lamports' => 5000000000,
	                'owner' => '11111111111111111111111111111111',
	                'data' => 'testdata',
	                'executable' => false,
	                'rentEpoch' => 300,
	            ],
	        ]);

	    $account = new Account($mockRpc);
	    $result = $account->getAccountInfo('4izNYzN7uQac8jBDcD7NmuCpS8PqvYiHVSLXF5bY9Zrg', [
	        'commitment' => 'finalized',
	        'encoding' => 'jsonParsed',
	    ]);

	    $this->assertIsArray($result);
	    $this->assertArrayHasKey('lamports', $result['value']);
	    $this->assertEquals(5000000000, $result['value']['lamports']);
	    $this->assertEquals('testdata', $result['value']['data']);
	    $this->assertFalse($result['value']['executable']);
	    $this->assertEquals(300, $result['value']['rentEpoch']);
	}




}
