<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use JosephOpanel\SolanaSDK\WebSocket\VoteSubscription;

class VoteSubscriptionTest extends TestCase
{
    public function testVoteSubscribe()
    {
        $subscription = new VoteSubscription();

        $result = $subscription->subscribe(function ($data) {
            $this->assertArrayHasKey('result', $data);
            echo "Received vote update: " . print_r($data, true);
        });

        $this->assertNotNull($result);
    }

    public function testVoteUnsubscribe()
    {
        $subscription = new VoteSubscription();

        $result = $subscription->unsubscribe(1); // Mock subscription ID
        $this->assertNotNull($result);
    }
}
