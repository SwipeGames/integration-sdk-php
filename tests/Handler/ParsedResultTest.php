<?php

declare(strict_types=1);

namespace SwipeGames\SDK\Tests\Handler;

use PHPUnit\Framework\TestCase;
use SwipeGames\SDK\Handler\ParsedResult;

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
        $error = ['message' => 'Something failed'];
        $result = ParsedResult::failure($error);
        $this->assertFalse($result->ok);
        $this->assertNull($result->body);
        $this->assertSame($error, $result->error);
    }
}
