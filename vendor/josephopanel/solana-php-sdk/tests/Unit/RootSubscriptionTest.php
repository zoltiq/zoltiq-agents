<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use JosephOpanel\SolanaSDK\WebSocket\RootSubscription;

class RootSubscriptionTest extends TestCase
{
    public function testRootSubscribe()
    {
        $subscription = new RootSubscription();

        $result = $subscription->subscribe(function ($data) {
            $this->assertArrayHasKey('result', $data);
            echo "Received root update: " . print_r($data, true);
        });

        $this->assertNotNull($result);
    }

    public function testRootUnsubscribe()
    {
        $subscription = new RootSubscription();

        $result = $subscription->unsubscribe(1); // Mock subscription ID
        $this->assertNotNull($result);
    }
}

