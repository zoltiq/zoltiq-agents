<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use JosephOpanel\SolanaSDK\WebSocket\AccountSubscription;

class AccountSubscriptionTest extends TestCase
{
    public function testAccountSubscribe()
    {
        $subscription = new AccountSubscription();

        $result = $subscription->subscribe(
            '4izNYzN7uQac8jBDcD7NmuCpS8PqvYiHVSLXF5bY9Zrg',
            function ($data) {
                $this->assertArrayHasKey('result', $data);
                echo "Received update: " . print_r($data, true);
            }
        );

        $this->assertNotNull($result);
    }

    public function testAccountUnsubscribe()
    {
        $subscription = new AccountSubscription();

        $result = $subscription->unsubscribe(1); // Mock subscription ID
        $this->assertNotNull($result);
    }
}
