<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use JosephOpanel\SolanaSDK\WebSocket\BlockSubscription;

class BlockSubscriptionTest extends TestCase
{
    public function testBlockSubscribe()
    {
        $subscription = new BlockSubscription();

        $result = $subscription->subscribe(
            function ($data) {
                $this->assertArrayHasKey('result', $data);
                echo "Received block update: " . print_r($data, true);
            },
            ['commitment' => 'finalized']
        );

        $this->assertNotNull($result);
    }

    public function testBlockUnsubscribe()
    {
        $subscription = new BlockSubscription();

        $result = $subscription->unsubscribe(1); // Mock subscription ID
        $this->assertNotNull($result);
    }
}
