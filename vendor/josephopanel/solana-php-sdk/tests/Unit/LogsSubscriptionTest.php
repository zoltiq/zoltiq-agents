<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use JosephOpanel\SolanaSDK\WebSocket\LogsSubscription;

class LogsSubscriptionTest extends TestCase
{
    public function testLogsSubscribe()
    {
        $subscription = new LogsSubscription();

        $result = $subscription->subscribe(
            '4izNYzN7uQac8jBDcD7NmuCpS8PqvYiHVSLXF5bY9Zrg',
            function ($data) {
                $this->assertArrayHasKey('result', $data);
                echo "Received logs update: " . print_r($data, true);
            },
            ['commitment' => 'finalized']
        );

        $this->assertNotNull($result);
    }

    public function testLogsUnsubscribe()
    {
        $subscription = new LogsSubscription();

        $result = $subscription->unsubscribe(1); // Mock subscription ID
        $this->assertNotNull($result);
    }
}
