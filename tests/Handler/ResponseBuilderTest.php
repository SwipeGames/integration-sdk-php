<?php

declare(strict_types=1);

namespace SwipeGames\SDK\Tests\Handler;

use PHPUnit\Framework\TestCase;
use SwipeGames\SDK\Handler\ResponseBuilder;

class ResponseBuilderTest extends TestCase
{
    public function testBalanceResponse(): void
    {
        $result = ResponseBuilder::balanceResponse('100.50');
        $this->assertSame(['balance' => '100.50'], $result);
    }

    public function testBetResponse(): void
    {
        $result = ResponseBuilder::betResponse('90.00', 'tx-123');
        $this->assertSame(['balance' => '90.00', 'txID' => 'tx-123'], $result);
    }

    public function testWinResponse(): void
    {
        $result = ResponseBuilder::winResponse('150.00', 'tx-456');
        $this->assertSame(['balance' => '150.00', 'txID' => 'tx-456'], $result);
    }

    public function testRefundResponse(): void
    {
        $result = ResponseBuilder::refundResponse('100.00', 'tx-789');
        $this->assertSame(['balance' => '100.00', 'txID' => 'tx-789'], $result);
    }

    public function testErrorResponseMinimal(): void
    {
        $result = ResponseBuilder::errorResponse('Something went wrong');
        $this->assertSame(['message' => 'Something went wrong'], $result);
    }

    public function testErrorResponseFull(): void
    {
        $result = ResponseBuilder::errorResponse(
            message: 'Insufficient funds',
            code: 'insufficient_funds',
            action: 'refresh',
            actionData: 'some-data',
            details: 'Balance is 0',
        );
        $this->assertSame([
            'message' => 'Insufficient funds',
            'code' => 'insufficient_funds',
            'action' => 'refresh',
            'actionData' => 'some-data',
            'details' => 'Balance is 0',
        ], $result);
    }

    public function testErrorResponseOmitsNullFields(): void
    {
        $result = ResponseBuilder::errorResponse(
            message: 'Error',
            code: 'session_expired',
        );
        $this->assertSame([
            'message' => 'Error',
            'code' => 'session_expired',
        ], $result);
        $this->assertArrayNotHasKey('action', $result);
        $this->assertArrayNotHasKey('actionData', $result);
        $this->assertArrayNotHasKey('details', $result);
    }
}
