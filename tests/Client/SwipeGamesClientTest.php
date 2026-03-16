<?php

declare(strict_types=1);

namespace SwipeGames\SDK\Tests\Client;

use PHPUnit\Framework\TestCase;
use SwipeGames\SDK\Client\ClientConfig;
use SwipeGames\SDK\Client\HttpClient;
use SwipeGames\SDK\Exception\SwipeGamesApiException;
use SwipeGames\SDK\Exception\SwipeGamesValidationException;
use SwipeGames\SDK\SwipeGamesClient;

class SwipeGamesClientTest extends TestCase
{
    private function makeConfig(string $env = 'staging', ?string $baseUrl = null): ClientConfig
    {
        return new ClientConfig(
            cid: 'test-cid',
            extCid: 'test-ext-cid',
            apiKey: 'test-api-key',
            integrationApiKey: 'test-integration-key',
            env: $env,
            baseUrl: $baseUrl,
        );
    }

    private function makeMockHttpClient(int $statusCode, string $body): HttpClient
    {
        $mock = $this->createMock(HttpClient::class);
        $mock->method('request')->willReturn([
            'statusCode' => $statusCode,
            'body' => $body,
        ]);
        return $mock;
    }

    // ── Constructor tests ──

    public function testDefaultsStagingEnvironment(): void
    {
        $client = new SwipeGamesClient($this->makeConfig());
        $this->assertInstanceOf(SwipeGamesClient::class, $client);
    }

    public function testProductionEnvironment(): void
    {
        $client = new SwipeGamesClient($this->makeConfig('production'));
        $this->assertInstanceOf(SwipeGamesClient::class, $client);
    }

    public function testCustomBaseUrl(): void
    {
        $client = new SwipeGamesClient($this->makeConfig(baseUrl: 'https://custom.example.com/api'));
        $this->assertInstanceOf(SwipeGamesClient::class, $client);
    }

    public function testUnknownEnvThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown env: unknown');
        new SwipeGamesClient($this->makeConfig('unknown'));
    }

    // ── createNewGame tests ──

    public function testCreateNewGameSuccess(): void
    {
        $httpClient = $this->createMock(HttpClient::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                $this->stringContains('/create-new-game'),
                $this->callback(function (array $options) {
                    $this->assertSame('application/json', $options['headers']['Content-Type']);
                    $this->assertArrayHasKey('X-REQUEST-SIGN', $options['headers']);

                    $body = json_decode($options['body'], true);
                    $this->assertSame('test-cid', $body['cID']);
                    $this->assertSame('test-ext-cid', $body['extCID']);
                    $this->assertSame('sg_catch_97', $body['gameID']);
                    return true;
                })
            )
            ->willReturn([
                'statusCode' => 200,
                'body' => '{"gameURL":"https://game.example.com","gsID":"abc-123"}',
            ]);

        $client = new SwipeGamesClient($this->makeConfig(), $httpClient);
        $result = $client->createNewGame([
            'gameID' => 'sg_catch_97',
            'demo' => false,
            'platform' => 'desktop',
            'currency' => 'USD',
            'locale' => 'en_us',
        ]);

        $this->assertSame('https://game.example.com', $result['gameURL']);
        $this->assertSame('abc-123', $result['gsID']);
    }

    public function testCreateNewGameApiError(): void
    {
        $httpClient = $this->makeMockHttpClient(401, '{"message":"Invalid signature"}');
        $client = new SwipeGamesClient($this->makeConfig(), $httpClient);

        $this->expectException(SwipeGamesApiException::class);
        $client->createNewGame([
            'gameID' => 'sg_catch_97',
            'demo' => false,
            'platform' => 'desktop',
            'currency' => 'USD',
            'locale' => 'en_us',
        ]);
    }

    // ── getGames tests ──

    public function testGetGamesSuccess(): void
    {
        $httpClient = $this->createMock(HttpClient::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->callback(function (string $url) {
                    $this->assertStringContainsString('/games', $url);
                    $this->assertStringContainsString('cID=test-cid', $url);
                    $this->assertStringContainsString('extCID=test-ext-cid', $url);
                    return true;
                }),
                $this->callback(function (array $options) {
                    $this->assertArrayHasKey('X-REQUEST-SIGN', $options['headers']);
                    return true;
                })
            )
            ->willReturn([
                'statusCode' => 200,
                'body' => '[{"id":"sg_catch_97","title":"Catch 97"}]',
            ]);

        $client = new SwipeGamesClient($this->makeConfig(), $httpClient);
        $result = $client->getGames();

        $this->assertCount(1, $result);
        $this->assertSame('sg_catch_97', $result[0]['id']);
    }

    // ── createFreeRounds tests ──

    public function testCreateFreeRoundsSuccess(): void
    {
        $httpClient = $this->makeMockHttpClient(200, '{"id":"fr-123","extID":"my-fr"}');
        $client = new SwipeGamesClient($this->makeConfig(), $httpClient);

        $result = $client->createFreeRounds([
            'extID' => 'my-fr',
            'currency' => 'USD',
            'quantity' => 10,
            'betLine' => 1,
            'validFrom' => '2026-01-01T00:00:00.000Z',
        ]);

        $this->assertSame('fr-123', $result['id']);
        $this->assertSame('my-fr', $result['extID']);
    }

    // ── cancelFreeRounds tests ──

    public function testCancelFreeRoundsSuccess(): void
    {
        $httpClient = $this->createMock(HttpClient::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with('DELETE', $this->stringContains('/free-rounds'), $this->anything())
            ->willReturn(['statusCode' => 200, 'body' => '']);

        $client = new SwipeGamesClient($this->makeConfig(), $httpClient);
        $client->cancelFreeRounds(['id' => 'fr-123']);

        $this->assertTrue(true); // no exception = success
    }

    public function testCancelFreeRoundsValidation(): void
    {
        $httpClient = $this->makeMockHttpClient(200, '');
        $client = new SwipeGamesClient($this->makeConfig(), $httpClient);

        $this->expectException(SwipeGamesValidationException::class);
        $client->cancelFreeRounds([]);
    }

    // ── Verify inbound requests ──

    public function testVerifyBetRequestValid(): void
    {
        $config = new ClientConfig(
            cid: 'cid', extCid: 'ext', apiKey: 'key',
            integrationApiKey: 'secret-key',
        );
        $client = new SwipeGamesClient($config);

        $body = '{"user_id": 123, "amount": 100.50}';
        $sig = '9876ed3affd6596f3ddb9102a396718452cf83069904f3d001a2e91e164adc01';

        $this->assertTrue($client->verifyBetRequest($body, $sig));
    }

    public function testVerifyBetRequestInvalid(): void
    {
        $config = new ClientConfig(
            cid: 'cid', extCid: 'ext', apiKey: 'key',
            integrationApiKey: 'secret-key',
        );
        $client = new SwipeGamesClient($config);

        $this->assertFalse($client->verifyBetRequest('{"test":1}', 'invalid-sig'));
    }

    public function testVerifyBetRequestMissingSignature(): void
    {
        $config = new ClientConfig(
            cid: 'cid', extCid: 'ext', apiKey: 'key',
            integrationApiKey: 'secret-key',
        );
        $client = new SwipeGamesClient($config);

        $this->assertFalse($client->verifyBetRequest('{"test":1}', null));
    }

    public function testVerifyBalanceRequestValid(): void
    {
        $config = new ClientConfig(
            cid: 'cid', extCid: 'ext', apiKey: 'key',
            integrationApiKey: 'secret-key',
        );
        $client = new SwipeGamesClient($config);

        $params = ['sessionID' => '7eaac66f751bcdb758877004b0a1c0063bdfb615ee0c20a464ae76edc67db324113f1ca8bd62b13dd1c7a43f85a20ea3'];
        $sig = '23b02858e21abd151a4e48ed33e451cae4ad1b7cb267ef75d01c694ea2960e6d';

        $this->assertTrue($client->verifyBalanceRequest($params, $sig));
    }

    // ── parseAndVerify tests ──

    public function testParseAndVerifyBetRequestSuccess(): void
    {
        $config = new ClientConfig(
            cid: 'cid', extCid: 'ext', apiKey: 'key',
            integrationApiKey: 'secret-key',
        );
        $client = new SwipeGamesClient($config);

        $body = '{"type":"regular","sessionID":"sess-1","amount":"10.00","txID":"tx-1","roundID":"round-1"}';
        $sig = \SwipeGames\SDK\Crypto\Signer::sign($body, 'secret-key');

        $result = $client->parseAndVerifyBetRequest($body, $sig);

        $this->assertTrue($result->ok);
        $this->assertSame('regular', $result->body['type']);
        $this->assertSame('sess-1', $result->body['sessionID']);
    }

    public function testParseAndVerifyBetRequestBadSignature(): void
    {
        $config = new ClientConfig(
            cid: 'cid', extCid: 'ext', apiKey: 'key',
            integrationApiKey: 'secret-key',
        );
        $client = new SwipeGamesClient($config);

        $result = $client->parseAndVerifyBetRequest('{"type":"regular"}', 'bad-sig');

        $this->assertFalse($result->ok);
        $this->assertSame('Invalid signature', $result->error['message']);
    }

    public function testParseAndVerifyBetRequestMissingFields(): void
    {
        $config = new ClientConfig(
            cid: 'cid', extCid: 'ext', apiKey: 'key',
            integrationApiKey: 'secret-key',
        );
        $client = new SwipeGamesClient($config);

        $body = '{"type":"regular"}';
        $sig = \SwipeGames\SDK\Crypto\Signer::sign($body, 'secret-key');

        $result = $client->parseAndVerifyBetRequest($body, $sig);

        $this->assertFalse($result->ok);
        $this->assertSame('Invalid request body', $result->error['message']);
    }

    public function testParseAndVerifyBetRequestInvalidType(): void
    {
        $config = new ClientConfig(
            cid: 'cid', extCid: 'ext', apiKey: 'key',
            integrationApiKey: 'secret-key',
        );
        $client = new SwipeGamesClient($config);

        $body = '{"type":"invalid","sessionID":"s","amount":"10","txID":"t","roundID":"r"}';
        $sig = \SwipeGames\SDK\Crypto\Signer::sign($body, 'secret-key');

        $result = $client->parseAndVerifyBetRequest($body, $sig);

        $this->assertFalse($result->ok);
    }

    public function testParseAndVerifyRefundRequestSuccess(): void
    {
        $config = new ClientConfig(
            cid: 'cid', extCid: 'ext', apiKey: 'key',
            integrationApiKey: 'secret-key',
        );
        $client = new SwipeGamesClient($config);

        $body = '{"sessionID":"sess-1","amount":"10.00","txID":"tx-1","origTxID":"orig-1"}';
        $sig = \SwipeGames\SDK\Crypto\Signer::sign($body, 'secret-key');

        $result = $client->parseAndVerifyRefundRequest($body, $sig);

        $this->assertTrue($result->ok);
        $this->assertSame('sess-1', $result->body['sessionID']);
    }

    public function testParseAndVerifyBalanceRequestSuccess(): void
    {
        $config = new ClientConfig(
            cid: 'cid', extCid: 'ext', apiKey: 'key',
            integrationApiKey: 'secret-key',
        );
        $client = new SwipeGamesClient($config);

        $params = ['sessionID' => 'sess-1'];
        $sig = \SwipeGames\SDK\Crypto\Signer::signQueryParams($params, 'secret-key');

        $result = $client->parseAndVerifyBalanceRequest($params, $sig);

        $this->assertTrue($result->ok);
        $this->assertSame('sess-1', $result->body['sessionID']);
    }

    public function testParseAndVerifyBalanceRequestMissingSessionId(): void
    {
        $config = new ClientConfig(
            cid: 'cid', extCid: 'ext', apiKey: 'key',
            integrationApiKey: 'secret-key',
        );
        $client = new SwipeGamesClient($config);

        $params = [];
        $sig = \SwipeGames\SDK\Crypto\Signer::signQueryParams($params, 'secret-key');

        $result = $client->parseAndVerifyBalanceRequest($params, $sig);

        $this->assertFalse($result->ok);
        $this->assertSame('Missing sessionID', $result->error['message']);
    }

    // ── Debug logging ──

    public function testDebugLoggingEnabled(): void
    {
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->atLeastOnce())->method('debug');

        $config = new ClientConfig(
            cid: 'test-cid', extCid: 'test-ext-cid',
            apiKey: 'test-api-key', integrationApiKey: 'test-key',
            debug: true, logger: $logger,
        );

        $httpClient = $this->makeMockHttpClient(200, '{"gameURL":"url","gsID":"id"}');
        $client = new SwipeGamesClient($config, $httpClient);

        $client->createNewGame([
            'gameID' => 'sg_catch_97',
            'demo' => false,
            'platform' => 'desktop',
            'currency' => 'USD',
            'locale' => 'en_us',
        ]);
    }

    public function testDebugLoggingDisabled(): void
    {
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->never())->method('debug');

        $config = new ClientConfig(
            cid: 'test-cid', extCid: 'test-ext-cid',
            apiKey: 'test-api-key', integrationApiKey: 'test-key',
            debug: false, logger: $logger,
        );

        $httpClient = $this->makeMockHttpClient(200, '{"gameURL":"url","gsID":"id"}');
        $client = new SwipeGamesClient($config, $httpClient);

        $client->createNewGame([
            'gameID' => 'sg_catch_97',
            'demo' => false,
            'platform' => 'desktop',
            'currency' => 'USD',
            'locale' => 'en_us',
        ]);
    }
}
