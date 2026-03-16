<?php

declare(strict_types=1);

namespace SwipeGames\SDK\Tests\Handler;

use PHPUnit\Framework\TestCase;
use SwipeGames\SDK\Handler\ResponseBuilder;
use SwipeGames\PublicApi\Integration\BalanceResponse;
use SwipeGames\PublicApi\Integration\BetResponse;
use SwipeGames\PublicApi\Integration\WinResponse;
use SwipeGames\PublicApi\Integration\RefundResponse;

class ResponseBuilderTest extends TestCase
{
    public function testBalanceResponse(): void
    {
        $result = ResponseBuilder::balanceResponse('100.50');
        $this->assertInstanceOf(BalanceResponse::class, $result);
        $this->assertSame('100.50', $result->getBalance());
    }

    public function testBetResponse(): void
    {
        $result = ResponseBuilder::betResponse('90.00', 'tx-123');
        $this->assertInstanceOf(BetResponse::class, $result);
        $this->assertSame('90.00', $result->getBalance());
        $this->assertSame('tx-123', $result->getTxId());
    }

    public function testWinResponse(): void
    {
        $result = ResponseBuilder::winResponse('150.00', 'tx-456');
        $this->assertInstanceOf(WinResponse::class, $result);
        $this->assertSame('150.00', $result->getBalance());
        $this->assertSame('tx-456', $result->getTxId());
    }

    public function testRefundResponse(): void
    {
        $result = ResponseBuilder::refundResponse('100.00', 'tx-789');
        $this->assertInstanceOf(RefundResponse::class, $result);
        $this->assertSame('100.00', $result->getBalance());
        $this->assertSame('tx-789', $result->getTxId());
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

    public function testResponsesAreJsonSerializable(): void
    {
        $balance = ResponseBuilder::balanceResponse('100.50');
        $json = json_encode($balance);
        $decoded = json_decode($json, true);
        $this->assertSame('100.50', $decoded['balance']);

        $bet = ResponseBuilder::betResponse('90.00', 'tx-123');
        $json = json_encode($bet);
        $decoded = json_decode($json, true);
        $this->assertSame('90.00', $decoded['balance']);
        $this->assertSame('tx-123', $decoded['txID']);
    }
}
