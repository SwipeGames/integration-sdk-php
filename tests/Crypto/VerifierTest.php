<?php

declare(strict_types=1);

namespace SwipeGames\SDK\Tests\Crypto;

use PHPUnit\Framework\TestCase;
use SwipeGames\SDK\Crypto\Verifier;

class VerifierTest extends TestCase
{
    public function testReturnsTrueForValidSignature(): void
    {
        $this->assertTrue(
            Verifier::verify(
                '{"user_id": 123, "amount": 100.50}',
                '9876ed3affd6596f3ddb9102a396718452cf83069904f3d001a2e91e164adc01',
                'secret-key'
            )
        );
    }

    public function testReturnsFalseForInvalidSignature(): void
    {
        $this->assertFalse(
            Verifier::verify(
                '{"user_id": 123, "amount": 100.50}',
                '0000000000000000000000000000000000000000000000000000000000000000',
                'secret-key'
            )
        );
    }

    public function testReturnsFalseForWrongKey(): void
    {
        $this->assertFalse(
            Verifier::verify(
                '{"user_id": 123, "amount": 100.50}',
                '9876ed3affd6596f3ddb9102a396718452cf83069904f3d001a2e91e164adc01',
                'wrong-key'
            )
        );
    }

    public function testReturnsTrueForValidQueryParamSignature(): void
    {
        $this->assertTrue(
            Verifier::verifyQueryParams(
                ['sessionID' => '7eaac66f751bcdb758877004b0a1c0063bdfb615ee0c20a464ae76edc67db324113f1ca8bd62b13dd1c7a43f85a20ea3'],
                '23b02858e21abd151a4e48ed33e451cae4ad1b7cb267ef75d01c694ea2960e6d',
                'secret-key'
            )
        );
    }

    public function testReturnsFalseForInvalidQueryParamSignature(): void
    {
        $this->assertFalse(
            Verifier::verifyQueryParams(
                ['sessionID' => 'test'],
                '0000000000000000000000000000000000000000000000000000000000000000',
                'secret-key'
            )
        );
    }
}
