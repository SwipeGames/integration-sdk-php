<?php

declare(strict_types=1);

namespace SwipeGames\SDK\Tests\Handler;

use PHPUnit\Framework\TestCase;
use SwipeGames\SDK\Handler\ResponseBuilder;
use SwipeGames\PublicApi\Integration\BalanceResponse;
use SwipeGames\PublicApi\Integration\BetResponse;
use SwipeGames\PublicApi\Integration\WinResponse;
use SwipeGames\PublicApi\Integration\RefundResponse;
use SwipeGames\PublicApi\Integration\ErrorResponseWithCodeAndAction;

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
        $this->assertInstanceOf(ErrorResponseWithCodeAndAction::class, $result);
        $this->assertSame('Something went wrong', $result->getMessage());
        $this->assertNull($result->getCode());
        $this->assertNull($result->getAction());
        $this->assertNull($result->getActionData());
        $this->assertNull($result->getDetails());
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
        $this->assertInstanceOf(ErrorResponseWithCodeAndAction::class, $result);
        $this->assertSame('Insufficient funds', $result->getMessage());
        $this->assertSame('insufficient_funds', $result->getCode());
        $this->assertSame('refresh', $result->getAction());
        $this->assertSame('some-data', $result->getActionData());
        $this->assertSame('Balance is 0', $result->getDetails());
    }

    public function testErrorResponseOmitsNullFields(): void
    {
        $result = ResponseBuilder::errorResponse(
            message: 'Error',
            code: 'session_expired',
        );
        $this->assertSame('Error', $result->getMessage());
        $this->assertSame('session_expired', $result->getCode());
        $this->assertNull($result->getAction());
        $this->assertNull($result->getActionData());
        $this->assertNull($result->getDetails());
    }

    public function testErrorResponseJsonOmitsNullFields(): void
    {
        $result = ResponseBuilder::errorResponse('Something went wrong');
        $json = json_encode($result);
        $decoded = json_decode($json, true);
        $this->assertSame('Something went wrong', $decoded['message']);
        $this->assertArrayNotHasKey('code', $decoded);
        $this->assertArrayNotHasKey('action', $decoded);
        $this->assertArrayNotHasKey('actionData', $decoded);
        $this->assertArrayNotHasKey('details', $decoded);
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

    public function testErrorResponseIsJsonSerializable(): void
    {
        $result = ResponseBuilder::errorResponse(
            message: 'Insufficient funds',
            code: 'insufficient_funds',
            action: 'refresh',
        );
        $json = json_encode($result);
        $decoded = json_decode($json, true);
        $this->assertSame('Insufficient funds', $decoded['message']);
        $this->assertSame('insufficient_funds', $decoded['code']);
        $this->assertSame('refresh', $decoded['action']);
    }
}
