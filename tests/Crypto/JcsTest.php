<?php

declare(strict_types=1);

namespace SwipeGames\SDK\Tests\Crypto;

use PHPUnit\Framework\TestCase;
use SwipeGames\SDK\Crypto\Jcs;

class JcsTest extends TestCase
{
    public function testSortsObjectKeys(): void
    {
        $result = Jcs::canonicalize('{"b": 2, "a": 1}');
        $this->assertSame('{"a":1,"b":2}', $result);
    }

    public function testSortsNestedObjectKeys(): void
    {
        $result = Jcs::canonicalize('{"b": {"d": 4, "c": 3}, "a": 1}');
        $this->assertSame('{"a":1,"b":{"c":3,"d":4}}', $result);
    }

    public function testSortsDeeplyNestedObjectKeys(): void
    {
        $result = Jcs::canonicalize('{"z": {"y": {"b": 2, "a": 1}}, "m": 0}');
        $this->assertSame('{"m":0,"z":{"y":{"a":1,"b":2}}}', $result);
    }

    public function testPreservesArrayOrder(): void
    {
        $result = Jcs::canonicalize('[3, 1, 2]');
        $this->assertSame('[3,1,2]', $result);
    }

    public function testHandlesArraysOfObjects(): void
    {
        $result = Jcs::canonicalize('[{"b":2,"a":1},{"d":4,"c":3}]');
        $this->assertSame('[{"a":1,"b":2},{"c":3,"d":4}]', $result);
    }

    public function testHandlesStrings(): void
    {
        $result = Jcs::canonicalize('{"key": "value"}');
        $this->assertSame('{"key":"value"}', $result);
    }

    public function testHandlesUnicodeStrings(): void
    {
        $result = Jcs::canonicalize('{"name": "André"}');
        $this->assertSame('{"name":"André"}', $result);
    }

    public function testHandlesEscapedCharacters(): void
    {
        $result = Jcs::canonicalize('{"msg": "hello\\nworld"}');
        $this->assertSame('{"msg":"hello\\nworld"}', $result);
    }

    public function testHandlesNullValues(): void
    {
        $result = Jcs::canonicalize('{"key": null}');
        $this->assertSame('{"key":null}', $result);
    }

    public function testHandlesBooleans(): void
    {
        $result = Jcs::canonicalize('{"t": true, "f": false}');
        $this->assertSame('{"f":false,"t":true}', $result);
    }

    public function testHandlesEmptyObject(): void
    {
        $result = Jcs::canonicalize('{}');
        $this->assertSame('{}', $result);
    }

    public function testHandlesEmptyArray(): void
    {
        $result = Jcs::canonicalize('[]');
        $this->assertSame('[]', $result);
    }

    public function testHandlesIntegerNumbers(): void
    {
        $result = Jcs::canonicalize('{"n": 42}');
        $this->assertSame('{"n":42}', $result);
    }

    public function testHandlesNegativeNumbers(): void
    {
        $result = Jcs::canonicalize('{"n": -42}');
        $this->assertSame('{"n":-42}', $result);
    }

    public function testHandlesZero(): void
    {
        $result = Jcs::canonicalize('{"n": 0}');
        $this->assertSame('{"n":0}', $result);
    }

    public function testHandlesDecimalNumbers(): void
    {
        $result = Jcs::canonicalize('{"n": 1.5}');
        $this->assertSame('{"n":1.5}', $result);
    }

    public function testHandlesNumberWithTrailingZero(): void
    {
        $result = Jcs::canonicalize('{"n": 100.50}');
        $this->assertSame('{"n":100.5}', $result);
    }

    public function testHandlesNegativeDecimal(): void
    {
        $result = Jcs::canonicalize('{"n": -3.14}');
        $this->assertSame('{"n":-3.14}', $result);
    }

    public function testStripsWhitespace(): void
    {
        $result = Jcs::canonicalize('{  "user_id"  :  123  ,  "amount"  :  100.50  }');
        $this->assertSame('{"amount":100.5,"user_id":123}', $result);
    }

    public function testAcceptsArrayInput(): void
    {
        $result = Jcs::canonicalize(['user_id' => 123, 'amount' => 100.5]);
        $this->assertSame('{"amount":100.5,"user_id":123}', $result);
    }

    public function testAcceptsArrayInputWithNestedObjects(): void
    {
        $result = Jcs::canonicalize([
            'user' => ['firstName' => 'John', 'id' => 'p1'],
            'gameID' => 'sg_catch_97',
        ]);
        $this->assertSame('{"gameID":"sg_catch_97","user":{"firstName":"John","id":"p1"}}', $result);
    }

    public function testAcceptsArrayInputWithNestedArrays(): void
    {
        $result = Jcs::canonicalize([
            'gameIDs' => ['sg_catch_97', 'sg_slots_42'],
            'currency' => 'USD',
        ]);
        $this->assertSame('{"currency":"USD","gameIDs":["sg_catch_97","sg_slots_42"]}', $result);
    }

    public function testMixedNestedStructure(): void
    {
        $input = '{"users":[{"name":"Bob","id":2},{"name":"Alice","id":1}],"meta":{"count":2,"active":true}}';
        $result = Jcs::canonicalize($input);
        $this->assertSame('{"meta":{"active":true,"count":2},"users":[{"id":2,"name":"Bob"},{"id":1,"name":"Alice"}]}', $result);
    }

    public function testThrowsOnInvalidJson(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Jcs::canonicalize('not valid json');
    }
}
