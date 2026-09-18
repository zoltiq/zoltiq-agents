<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use JosephOpanel\SolanaSDK\WebSocket\SignatureSubscription;

class SignatureSubscriptionTest extends TestCase
{
    public function testSignatureSubscribe()
    {
        $subscription = new SignatureSubscription();

        $result = $subscription->subscribe(
            '5yJg7hzSYfz6n9p8Nnh6PtqVjfzZKz8Xf7LvUgJb7Bm',
            function ($data) {
                $this->assertArrayHasKey('result', $data);
                echo "Received signature update: " . print_r($data, true);
            },
            ['commitment' => 'finalized']
        );

        $this->assertNotNull($result);
    }

    public function testSignatureUnsubscribe()
    {
        $subscription = new SignatureSubscription();

        $result = $subscription->unsubscribe(1); // Mock subscription ID
        $this->assertNotNull($result);
    }
}

