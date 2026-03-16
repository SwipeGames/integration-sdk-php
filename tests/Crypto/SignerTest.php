<?php

declare(strict_types=1);

namespace SwipeGames\SDK\Tests\Crypto;

use PHPUnit\Framework\TestCase;
use SwipeGames\SDK\Crypto\Signer;

/**
 * Test vectors from Go RequestSigner tests (platform-lib-common/utils/request-signer_test.go).
 */
class SignerTest extends TestCase
{
    public function testSignsJsonObjectWithJcsCanonicalization(): void
    {
        $sig = Signer::sign('{"user_id": 123, "amount": 100.50}', 'secret-key');
        $this->assertSame(
            '9876ed3affd6596f3ddb9102a396718452cf83069904f3d001a2e91e164adc01',
            $sig
        );
    }

    public function testProducesSameSignatureRegardlessOfKeyOrder(): void
    {
        $sig = Signer::sign('{"amount":100.5,"user_id":123}', 'secret-key');
        $this->assertSame(
            '9876ed3affd6596f3ddb9102a396718452cf83069904f3d001a2e91e164adc01',
            $sig
        );
    }

    public function testSignsEmptyObject(): void
    {
        $sig = Signer::sign('{}', 'secret-key');
        $this->assertSame(
            '99922a0dbb1fe95624c93c7204445c2eff8a014b0c9b585ddf2da0c21083a34e',
            $sig
        );
    }

    public function testHandlesWhitespaceInJsonInput(): void
    {
        $sig = Signer::sign('{  "user_id"  :  123  ,  "amount"  :  100.50  }', 'secret-key');
        $this->assertSame(
            '9876ed3affd6596f3ddb9102a396718452cf83069904f3d001a2e91e164adc01',
            $sig
        );
    }

    public function testDifferentKeyProducesDifferentSignature(): void
    {
        $sig = Signer::sign('{"user_id": 123, "amount": 100.50}', 'different-secret-key');
        $this->assertSame(
            'd86208a306f6562c80c0a8894a1294a63e5e3bb4e2fd2b9b031b3c3c65cb1847',
            $sig
        );
    }

    public function testWorksWithEmptyKey(): void
    {
        $sig = Signer::sign('{"test": "value"}', '');
        $this->assertSame(
            '6c0e6084444acce7905532fd7c3871c33cfbc5f52a36d27704ffa02b1bb4df78',
            $sig
        );
    }

    public function testAcceptsArrayInput(): void
    {
        $sig = Signer::sign(['user_id' => 123, 'amount' => 100.5], 'secret-key');
        $this->assertSame(
            '9876ed3affd6596f3ddb9102a396718452cf83069904f3d001a2e91e164adc01',
            $sig
        );
    }

    // ── Query params signature tests ──

    public function testSignsSingleQueryParam(): void
    {
        $sig = Signer::signQueryParams(
            ['sessionID' => '7eaac66f751bcdb758877004b0a1c0063bdfb615ee0c20a464ae76edc67db324113f1ca8bd62b13dd1c7a43f85a20ea3'],
            'secret-key'
        );
        $this->assertSame(
            '23b02858e21abd151a4e48ed33e451cae4ad1b7cb267ef75d01c694ea2960e6d',
            $sig
        );
    }

    public function testSignsEmptyQueryParams(): void
    {
        $sig = Signer::signQueryParams([], 'secret-key');
        $this->assertSame(
            '99922a0dbb1fe95624c93c7204445c2eff8a014b0c9b585ddf2da0c21083a34e',
            $sig
        );
    }

    public function testSignsMultipleQueryParamsWithSpecialCharacters(): void
    {
        $sig = Signer::signQueryParams(
            ['message' => 'hello world!', 'data' => 'test@example.com'],
            'secret-key'
        );
        $this->assertSame(
            '0825b42e92c46887f194252fda8b871c3c42aafa3833783d63b2005407000c02',
            $sig
        );
    }

    public function testHandlesEmptyParamValue(): void
    {
        $sig = Signer::signQueryParams(
            ['empty' => '', 'data' => 'value'],
            'secret-key'
        );
        $this->assertSame(
            '8cf8644bfb7004cd21ad8512923169bb652d836183c07497797ef1ca313d88cc',
            $sig
        );
    }

    public function testQueryParamsWorksWithEmptyKey(): void
    {
        $sig = Signer::signQueryParams(['test' => 'value'], '');
        $this->assertSame(
            '6c0e6084444acce7905532fd7c3871c33cfbc5f52a36d27704ffa02b1bb4df78',
            $sig
        );
    }
}
