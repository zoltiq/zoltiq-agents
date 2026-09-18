<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use JosephOpanel\SolanaSDK\WebSocket\SlotsUpdatesSubscription;

class SlotsUpdatesSubscriptionTest extends TestCase
{
    public function testSlotsUpdatesSubscribe()
    {
        $subscription = new SlotsUpdatesSubscription();

        $result = $subscription->subscribe(function ($data) {
            $this->assertArrayHasKey('result', $data);
            echo "Received slots updates: " . print_r($data, true);
        });

        $this->assertNotNull($result);
    }

    public function testSlotsUpdatesUnsubscribe()
    {
        $subscription = new SlotsUpdatesSubscription();

        $result = $subscription->unsubscribe(1); // Mock subscription ID
        $this->assertNotNull($result);
    }
}
