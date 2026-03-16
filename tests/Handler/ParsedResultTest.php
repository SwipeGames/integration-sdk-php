<?php

declare(strict_types=1);

namespace SwipeGames\SDK\Tests\Handler;

use PHPUnit\Framework\TestCase;
use SwipeGames\SDK\Handler\ParsedResult;
use SwipeGames\SDK\Handler\ResponseBuilder;
use SwipeGames\PublicApi\Integration\ErrorResponseWithCodeAndAction;

class ParsedResultTest extends TestCase
{
    public function testSuccessResult(): void
    {
        $result = ParsedResult::success(['key' => 'value']);
        $this->assertTrue($result->ok);
        $this->assertSame(['key' => 'value'], $result->body);
        $this->assertNull($result->error);
    }

    public function testFailureResult(): void
    {
        $error = ResponseBuilder::errorResponse('Something failed');
        $result = ParsedResult::failure($error);
        $this->assertFalse($result->ok);
        $this->assertNull($result->body);
        $this->assertInstanceOf(ErrorResponseWithCodeAndAction::class, $result->error);
        $this->assertSame('Something failed', $result->error->getMessage());
    }
}
