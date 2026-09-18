<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use JosephOpanel\SolanaSDK\WebSocket\ProgramSubscription;

class ProgramSubscriptionTest extends TestCase
{
    public function testProgramSubscribe()
    {
        $subscription = new ProgramSubscription();

        $result = $subscription->subscribe(
            '4izNYzN7uQac8jBDcD7NmuCpS8PqvYiHVSLXF5bY9Zrg',
            function ($data) {
                $this->assertArrayHasKey('result', $data);
                echo "Received program update: " . print_r($data, true);
            },
            ['commitment' => 'finalized']
        );

        $this->assertNotNull($result);
    }

    public function testProgramUnsubscribe()
    {
        $subscription = new ProgramSubscription();

        $result = $subscription->unsubscribe(1); // Mock subscription ID
        $this->assertNotNull($result);
    }
}
