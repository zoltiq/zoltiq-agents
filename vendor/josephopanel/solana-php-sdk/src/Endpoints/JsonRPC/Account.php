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
use StephenHill\Base58;

class Account {
    private $rpc;

    public function __construct(SolanaRPC $rpc) {
        $this->rpc = $rpc;
    }

    public function getAccountInfo($account, array $options = ['commitment' => 'processed', 'encoding' => 'base64']): array {
        // Convert account to Base58 if it's a plain string
        if (is_string($account)) {
            $account = $this->validateAndConvertToBase58($account);
        }

        // Validate that account is now Base58 encoded
        if (!$this->isValidBase58($account)) {
            return [
                'error' => true,
                'code' => 1001,
                'message' => 'Invalid public key provided.',
            ];
        }

        // Perform the RPC call
        try {
            return $this->rpc->call('getAccountInfo', [$account, $options]);
        } catch (\Exception $e) {
            return [
                'error' => true,
                'code' => 1002,
                'message' => 'RPC error: ' . $e->getMessage(),
            ];
        }
    }

    public function getBalance(string $publicKey, string $commitment = 'finalized'): array {
        return $this->rpc->call('getBalance', [$publicKey, ['commitment' => $commitment]]);
    }

    public function getMultipleAccounts(array $addresses, array $config = []): array {
        $params = [$addresses];
        if (!empty($config)) {
            $params[] = $config;
        }
        return $this->rpc->call('getMultipleAccounts', $params);
    }

    public function getProgramAccounts(string $programId, array $config = []): array {
        // Validate program ID
        if (!$this->isValidBase58($programId)) {
            return [
                'error' => true,
                'code' => 1001,
                'message' => 'Invalid program ID provided.',
            ];
        }

        // Build parameters
        $params = [$programId];

        if (!empty($config)) {
            // Check for filters or dataSlice in the config
            if (isset($config['filters'])) {
                foreach ($config['filters'] as &$filter) {
                    if (isset($filter['memcmp']['bytes'])) {
                        // Validate Base58 for memcmp bytes
                        if (!$this->isValidBase58($filter['memcmp']['bytes'])) {
                            return [
                                'error' => true,
                                'code' => 1003,
                                'message' => 'Invalid Base58 data in memcmp filter.',
                            ];
                        }
                    }
                }
            }

            $params[] = $config; // Add config to params only if provided
        }

        // Perform RPC call
        try {
            return $this->rpc->call('getProgramAccounts', $params);
        } catch (\Exception $e) {
            return [
                'error' => true,
                'code' => 1002,
                'message' => 'RPC error: ' . $e->getMessage(),
            ];
        }
    }


    public function getTokenAccountBalance(string $address, string $commitment = 'finalized'): array {
        $response = $this->rpc->call('getTokenAccountBalance', [$address, ['commitment' => $commitment]]);
        return $response ?? []; // Return the response or an empty array
    }

    public function getTokenAccountsByDelegate(string $delegate, array $config = []): array {
        $params = [
            $delegate, // The delegate public key
        ];

        $options = [];

        // Add the SPL Token Program ID by default if not explicitly provided
        $options['programId'] = $config['programId'] ?? 'TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA';

        // If a mint is specified, add it as a filter
        if (!empty($config['mint'])) {
            $options['filters'] = [
                [
                    'memcmp' => [
                        'offset' => 0,
                        'bytes' => $config['mint'],
                    ],
                ],
            ];
        }

        // Include any additional options (e.g., encoding, commitment)
        foreach (['encoding', 'commitment'] as $key) {
            if (!empty($config[$key])) {
                $options[$key] = $config[$key];
            }
        }

        $params[] = $options;

        // Perform the RPC call
        return $this->rpc->call('getTokenAccountsByDelegate', $params);
    }

    public function getTokenAccountsByOwner(string $owner, array $config = []): array {
        $params = [$owner];

        // Ensure only valid keys are sent
        $validKeys = ['mint', 'programId'];
        $configKey = array_intersect_key($config, array_flip($validKeys));

        if (count($configKey) !== 1) {
            throw new InvalidArgumentException('Invalid config: specify exactly one of "mint" or "programId".');
        }

        $params[] = $configKey;

        // Add the encoding parameter
        $params[] = ['encoding' => 'jsonParsed'];

        // Perform the RPC call
        return $this->rpc->call('getTokenAccountsByOwner', $params);
    }


    public function getTokenLargestAccounts(string $mint, string $commitment = 'finalized'): array {
        $response = $this->rpc->call('getTokenLargestAccounts', [$mint, ['commitment' => $commitment]]);
        return $response['value'] ?? []; // Safely return the 'value' key or an empty array
    }

    public function getTokenSupply(string $mint, string $commitment = 'finalized'): array {
        $response = $this->rpc->call('getTokenSupply', [$mint, ['commitment' => $commitment]]);
        return $response['value'] ?? []; // Safely return the 'value' key or an empty array
    }

    /**
     * Validates and converts the input string to a Base58-encoded public key if necessary.
     *
     * @param string $input
     * @return string
     * @throws Exception
     */
    private function validateAndConvertToBase58(string $input): string
    {
        if ($this->isValidBase58($input)) {
            return $input;
        }

        // If the input is a hex string, decode it and re-encode as Base58
        if (ctype_xdigit($input)) {
            $binaryData = hex2bin($input);
            return $this->toBase58($binaryData);
        }

        throw new Exception("Invalid input: The provided input is neither a valid Base58 public key nor convertible to Base58.");
    }

    /**
     * Checks if a string is valid Base58.
     *
     * @param string $string
     * @return bool
     */
    private function isValidBase58(string $string): bool
    {
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        return (strspn($string, $alphabet) === strlen($string));
    }

    /**
     * Converts binary data to a Base58 string.
     *
     * @param string $binaryData
     * @return string
     */
    private function toBase58(string $binaryData): string
    {
        $base58 = new Base58();
        return $base58->encode($binaryData);
    }

}
