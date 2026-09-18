<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use JosephOpanel\SolanaSDK\WebSocket\SlotSubscription;

class SlotSubscriptionTest extends TestCase
{
    public function testSlotSubscribe()
    {
        $subscription = new SlotSubscription();

        $result = $subscription->subscribe(function ($data) {
            $this->assertArrayHasKey('result', $data);
            echo "Received slot update: " . print_r($data, true);
        });

        $this->assertNotNull($result);
    }

    public function testSlotUnsubscribe()
    {
        $subscription = new SlotSubscription();

        $result = $subscription->unsubscribe(1); // Mock subscription ID
        $this->assertNotNull($result);
    }
}
