<?php

declare(strict_types=1);

namespace SwipeGames\SDK\Tests\Client;

use PHPUnit\Framework\TestCase;
use SwipeGames\SDK\Client\ClientConfig;
use SwipeGames\SDK\Client\HttpClient;
use SwipeGames\SDK\Crypto\Signer;
use SwipeGames\SDK\Exception\SwipeGamesApiException;
use SwipeGames\SDK\Exception\SwipeGamesValidationException;
use SwipeGames\SDK\SwipeGamesClient;
use SwipeGames\PublicApi\Core\CreateNewGameResponse;
use SwipeGames\PublicApi\Core\CreateFreeRoundsResponse;
use SwipeGames\PublicApi\Core\GameInfo;
use SwipeGames\PublicApi\Integration\BetRequest;
use SwipeGames\PublicApi\Integration\WinRequest;
use SwipeGames\PublicApi\Integration\RefundRequest;
use SwipeGames\PublicApi\Integration\ErrorResponseWithCodeAndAction;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;

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

    private function makeIntegrationConfig(): ClientConfig
    {
        return new ClientConfig(
            cid: 'cid',
            extCid: 'ext',
            apiKey: 'key',
            integrationApiKey: 'secret-key',
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

        $this->assertInstanceOf(CreateNewGameResponse::class, $result);
        $this->assertSame('https://game.example.com', $result->getGameUrl());
        $this->assertSame('abc-123', $result->getGsId());
    }

    public function testCreateNewGameWithOptionalFields(): void
    {
        $httpClient = $this->createMock(HttpClient::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                $this->stringContains('/create-new-game'),
                $this->callback(function (array $options) {
                    $body = json_decode($options['body'], true);
                    $this->assertSame('sess-123', $body['sessionID']);
                    $this->assertSame('https://return.example.com', $body['returnURL']);
                    $this->assertSame('https://deposit.example.com', $body['depositURL']);
                    $this->assertSame('5000', $body['initDemoBalance']);
                    $this->assertSame('player-1', $body['user']['id']);
                    $this->assertSame('John', $body['user']['firstName']);
                    return true;
                })
            )
            ->willReturn([
                'statusCode' => 200,
                'body' => '{"gameURL":"https://game.example.com","gsID":"abc-123"}',
            ]);

        $client = new SwipeGamesClient($this->makeConfig(), $httpClient);
        $client->createNewGame([
            'gameID' => 'sg_catch_97',
            'demo' => true,
            'platform' => 'mobile',
            'currency' => 'USD',
            'locale' => 'en_us',
            'sessionID' => 'sess-123',
            'returnURL' => 'https://return.example.com',
            'depositURL' => 'https://deposit.example.com',
            'initDemoBalance' => '5000',
            'user' => ['id' => 'player-1', 'firstName' => 'John'],
        ]);
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
                'body' => '[{"id":"sg_catch_97","title":"Catch 97","locales":["en_us"],"currencies":["USD"],"platforms":["desktop"],"images":{"baseURL":"https://cdn.example.com","square":"/sq.png","horizontal":"/h.png","widescreen":"/w.png","vertical":"/v.png"},"hasFreeSpins":true,"rtp":97}]',
            ]);

        $client = new SwipeGamesClient($this->makeConfig(), $httpClient);
        $result = $client->getGames();

        $this->assertCount(1, $result);
        $this->assertInstanceOf(GameInfo::class, $result[0]);
        $this->assertSame('sg_catch_97', $result[0]->getId());
        $this->assertSame('Catch 97', $result[0]->getTitle());
    }

    public function testGetGamesApiError(): void
    {
        $httpClient = $this->makeMockHttpClient(401, '{"message":"Wrong signature"}');
        $client = new SwipeGamesClient($this->makeConfig(), $httpClient);

        $this->expectException(SwipeGamesApiException::class);
        $client->getGames();
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

        $this->assertInstanceOf(CreateFreeRoundsResponse::class, $result);
        $this->assertSame('fr-123', $result->getId());
        $this->assertSame('my-fr', $result->getExtId());
    }

    public function testCreateFreeRoundsWithOptionalFields(): void
    {
        $httpClient = $this->createMock(HttpClient::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                $this->stringContains('/free-rounds'),
                $this->callback(function (array $options) {
                    $body = json_decode($options['body'], true);
                    $this->assertSame(['sg_catch_97', 'sg_slots_42'], $body['gameIDs']);
                    $this->assertSame(['player-1', 'player-2'], $body['userIDs']);
                    $this->assertSame('2026-02-01T00:00:00.000Z', $body['validUntil']);
                    return true;
                })
            )
            ->willReturn([
                'statusCode' => 200,
                'body' => '{"id":"fr-456","extID":"my-fr-2"}',
            ]);

        $client = new SwipeGamesClient($this->makeConfig(), $httpClient);
        $client->createFreeRounds([
            'extID' => 'my-fr-2',
            'currency' => 'USD',
            'quantity' => 5,
            'betLine' => 2,
            'validFrom' => '2026-01-01T00:00:00.000Z',
            'gameIDs' => ['sg_catch_97', 'sg_slots_42'],
            'userIDs' => ['player-1', 'player-2'],
            'validUntil' => '2026-02-01T00:00:00.000Z',
        ]);
    }

    public function testCreateFreeRoundsApiError(): void
    {
        $httpClient = $this->makeMockHttpClient(409, '{"message":"Campaign already exists"}');
        $client = new SwipeGamesClient($this->makeConfig(), $httpClient);

        $this->expectException(SwipeGamesApiException::class);
        $client->createFreeRounds([
            'extID' => 'dup',
            'currency' => 'USD',
            'quantity' => 10,
            'betLine' => 1,
            'validFrom' => '2026-01-01T00:00:00.000Z',
        ]);
    }

    // ── cancelFreeRounds tests ──

    public function testCancelFreeRoundsById(): void
    {
        $httpClient = $this->createMock(HttpClient::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with('DELETE', $this->stringContains('/free-rounds'), $this->callback(function (array $options) {
                $body = json_decode($options['body'], true);
                $this->assertSame('fr-123', $body['id']);
                $this->assertArrayNotHasKey('extID', $body);
                return true;
            }))
            ->willReturn(['statusCode' => 200, 'body' => '']);

        $client = new SwipeGamesClient($this->makeConfig(), $httpClient);
        $client->cancelFreeRounds(['id' => 'fr-123']);
    }

    public function testCancelFreeRoundsByExtId(): void
    {
        $httpClient = $this->createMock(HttpClient::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with('DELETE', $this->stringContains('/free-rounds'), $this->callback(function (array $options) {
                $body = json_decode($options['body'], true);
                $this->assertSame('my-campaign', $body['extID']);
                $this->assertArrayNotHasKey('id', $body);
                return true;
            }))
            ->willReturn(['statusCode' => 200, 'body' => '']);

        $client = new SwipeGamesClient($this->makeConfig(), $httpClient);
        $client->cancelFreeRounds(['extID' => 'my-campaign']);
    }

    public function testCancelFreeRoundsValidation(): void
    {
        $httpClient = $this->makeMockHttpClient(200, '');
        $client = new SwipeGamesClient($this->makeConfig(), $httpClient);

        $this->expectException(SwipeGamesValidationException::class);
        $client->cancelFreeRounds([]);
    }

    public function testCancelFreeRoundsApiError(): void
    {
        $httpClient = $this->makeMockHttpClient(404, '{"message":"Campaign not found"}');
        $client = new SwipeGamesClient($this->makeConfig(), $httpClient);

        $this->expectException(SwipeGamesApiException::class);
        $client->cancelFreeRounds(['id' => 'nonexistent']);
    }

    // ── Network error wrapping ──

    public function testNetworkErrorWrappedInApiException(): void
    {
        $httpClient = $this->createMock(HttpClient::class);
        $httpClient->method('request')
            ->willThrowException(new ConnectException('Connection refused', new Request('POST', 'http://test')));

        $client = new SwipeGamesClient($this->makeConfig(), $httpClient);

        $this->expectException(SwipeGamesApiException::class);
        $this->expectExceptionMessage('Connection refused');
        $client->createNewGame([
            'gameID' => 'sg_catch_97',
            'demo' => false,
            'platform' => 'desktop',
            'currency' => 'USD',
            'locale' => 'en_us',
        ]);
    }

    public function testNetworkErrorWrappedInApiExceptionForGet(): void
    {
        $httpClient = $this->createMock(HttpClient::class);
        $httpClient->method('request')
            ->willThrowException(new ConnectException('DNS resolution failed', new Request('GET', 'http://test')));

        $client = new SwipeGamesClient($this->makeConfig(), $httpClient);

        $this->expectException(SwipeGamesApiException::class);
        $client->getGames();
    }

    // ── Verify inbound requests ──

    public function testVerifyBetRequestValid(): void
    {
        $client = new SwipeGamesClient($this->makeIntegrationConfig());

        $body = '{"user_id": 123, "amount": 100.50}';
        $sig = '9876ed3affd6596f3ddb9102a396718452cf83069904f3d001a2e91e164adc01';

        $this->assertTrue($client->verifyBetRequest($body, $sig));
    }

    public function testVerifyBetRequestInvalid(): void
    {
        $client = new SwipeGamesClient($this->makeIntegrationConfig());
        $this->assertFalse($client->verifyBetRequest('{"test":1}', 'invalid-sig'));
    }

    public function testVerifyBetRequestMissingSignature(): void
    {
        $client = new SwipeGamesClient($this->makeIntegrationConfig());
        $this->assertFalse($client->verifyBetRequest('{"test":1}', null));
    }

    public function testVerifyWinRequestValid(): void
    {
        $client = new SwipeGamesClient($this->makeIntegrationConfig());

        $body = '{"type":"regular","sessionID":"sess-1","amount":"50.00"}';
        $sig = Signer::sign($body, 'secret-key');

        $this->assertTrue($client->verifyWinRequest($body, $sig));
    }

    public function testVerifyWinRequestInvalid(): void
    {
        $client = new SwipeGamesClient($this->makeIntegrationConfig());
        $this->assertFalse($client->verifyWinRequest('{"test":1}', 'bad-sig'));
    }

    public function testVerifyRefundRequestValid(): void
    {
        $client = new SwipeGamesClient($this->makeIntegrationConfig());

        $body = '{"sessionID":"sess-1","amount":"10.00","txID":"tx-1","origTxID":"tx-0"}';
        $sig = Signer::sign($body, 'secret-key');

        $this->assertTrue($client->verifyRefundRequest($body, $sig));
    }

    public function testVerifyRefundRequestInvalid(): void
    {
        $client = new SwipeGamesClient($this->makeIntegrationConfig());
        $this->assertFalse($client->verifyRefundRequest('{"test":1}', 'bad-sig'));
    }

    public function testVerifyBalanceRequestValid(): void
    {
        $client = new SwipeGamesClient($this->makeIntegrationConfig());

        $params = ['sessionID' => '7eaac66f751bcdb758877004b0a1c0063bdfb615ee0c20a464ae76edc67db324113f1ca8bd62b13dd1c7a43f85a20ea3'];
        $sig = '23b02858e21abd151a4e48ed33e451cae4ad1b7cb267ef75d01c694ea2960e6d';

        $this->assertTrue($client->verifyBalanceRequest($params, $sig));
    }

    public function testVerifyBalanceRequestMissingSignature(): void
    {
        $client = new SwipeGamesClient($this->makeIntegrationConfig());
        $this->assertFalse($client->verifyBalanceRequest(['sessionID' => 'x'], null));
    }

    public function testVerifyBalanceRequestEmptySignature(): void
    {
        $client = new SwipeGamesClient($this->makeIntegrationConfig());
        $this->assertFalse($client->verifyBalanceRequest(['sessionID' => 'x'], ''));
    }

    // ── parseAndVerify tests ──

    public function testParseAndVerifyBetRequestSuccess(): void
    {
        $client = new SwipeGamesClient($this->makeIntegrationConfig());

        $body = '{"type":"regular","sessionID":"sess-1","amount":"10.00","txID":"c27ccade-5a45-4157-a85f-7d023a689ea5","roundID":"b78e42f8-2041-482d-9c4b-f2ca79fc75e3"}';
        $sig = Signer::sign($body, 'secret-key');

        $result = $client->parseAndVerifyBetRequest($body, $sig);

        $this->assertTrue($result->ok);
        $this->assertInstanceOf(BetRequest::class, $result->body);
        $this->assertSame('regular', $result->body->getType());
        $this->assertSame('sess-1', $result->body->getSessionId());
        $this->assertSame('10.00', $result->body->getAmount());
    }

    public function testParseAndVerifyBetRequestBadSignature(): void
    {
        $client = new SwipeGamesClient($this->makeIntegrationConfig());

        $result = $client->parseAndVerifyBetRequest('{"type":"regular"}', 'bad-sig');

        $this->assertFalse($result->ok);
        $this->assertInstanceOf(ErrorResponseWithCodeAndAction::class, $result->error);
        $this->assertSame('Invalid signature', $result->error->getMessage());
    }

    public function testParseAndVerifyBetRequestMissingFields(): void
    {
        $client = new SwipeGamesClient($this->makeIntegrationConfig());

        $body = '{"type":"regular"}';
        $sig = Signer::sign($body, 'secret-key');

        $result = $client->parseAndVerifyBetRequest($body, $sig);

        $this->assertFalse($result->ok);
        $this->assertSame('Invalid request body', $result->error->getMessage());
    }

    public function testParseAndVerifyBetRequestInvalidType(): void
    {
        $client = new SwipeGamesClient($this->makeIntegrationConfig());

        $body = '{"type":"invalid","sessionID":"s","amount":"10.00","txID":"c27ccade-5a45-4157-a85f-7d023a689ea5","roundID":"b78e42f8-2041-482d-9c4b-f2ca79fc75e3"}';
        $sig = Signer::sign($body, 'secret-key');

        $result = $client->parseAndVerifyBetRequest($body, $sig);

        // Generated type throws on invalid enum value during deserialization
        $this->assertFalse($result->ok);
    }

    public function testParseAndVerifyWinRequestSuccess(): void
    {
        $client = new SwipeGamesClient($this->makeIntegrationConfig());

        $body = '{"type":"regular","sessionID":"sess-1","amount":"50.00","txID":"c27ccade-5a45-4157-a85f-7d023a689ea5","roundID":"b78e42f8-2041-482d-9c4b-f2ca79fc75e3"}';
        $sig = Signer::sign($body, 'secret-key');

        $result = $client->parseAndVerifyWinRequest($body, $sig);

        $this->assertTrue($result->ok);
        $this->assertInstanceOf(WinRequest::class, $result->body);
        $this->assertSame('regular', $result->body->getType());
    }

    public function testParseAndVerifyWinRequestBadSignature(): void
    {
        $client = new SwipeGamesClient($this->makeIntegrationConfig());

        $result = $client->parseAndVerifyWinRequest('{"type":"regular"}', 'bad-sig');

        $this->assertFalse($result->ok);
        $this->assertSame('Invalid signature', $result->error->getMessage());
    }

    public function testParseAndVerifyRefundRequestSuccess(): void
    {
        $client = new SwipeGamesClient($this->makeIntegrationConfig());

        $body = '{"sessionID":"sess-1","amount":"10.00","txID":"c27ccade-5a45-4157-a85f-7d023a689ea5","origTxID":"b78e42f8-2041-482d-9c4b-f2ca79fc75e3"}';
        $sig = Signer::sign($body, 'secret-key');

        $result = $client->parseAndVerifyRefundRequest($body, $sig);

        $this->assertTrue($result->ok);
        $this->assertInstanceOf(RefundRequest::class, $result->body);
        $this->assertSame('sess-1', $result->body->getSessionId());
    }

    public function testParseAndVerifyRefundRequestBadSignature(): void
    {
        $client = new SwipeGamesClient($this->makeIntegrationConfig());

        $result = $client->parseAndVerifyRefundRequest('{"sessionID":"s"}', 'bad-sig');

        $this->assertFalse($result->ok);
        $this->assertSame('Invalid signature', $result->error->getMessage());
    }

    public function testParseAndVerifyBalanceRequestSuccess(): void
    {
        $client = new SwipeGamesClient($this->makeIntegrationConfig());

        $params = ['sessionID' => 'sess-1'];
        $sig = Signer::signQueryParams($params, 'secret-key');

        $result = $client->parseAndVerifyBalanceRequest($params, $sig);

        $this->assertTrue($result->ok);
        $this->assertSame('sess-1', $result->body['sessionID']);
    }

    public function testParseAndVerifyBalanceRequestMissingSessionId(): void
    {
        $client = new SwipeGamesClient($this->makeIntegrationConfig());

        $params = [];
        $sig = Signer::signQueryParams($params, 'secret-key');

        $result = $client->parseAndVerifyBalanceRequest($params, $sig);

        $this->assertFalse($result->ok);
        $this->assertSame('Missing sessionID', $result->error->getMessage());
    }

    public function testParseAndVerifyBalanceRequestBadSignature(): void
    {
        $client = new SwipeGamesClient($this->makeIntegrationConfig());

        $result = $client->parseAndVerifyBalanceRequest(['sessionID' => 'sess-1'], 'bad-sig');

        $this->assertFalse($result->ok);
        $this->assertSame('Invalid signature', $result->error->getMessage());
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

    // ── Configurable timeout ──

    public function testCustomTimeoutPassedToConfig(): void
    {
        $config = new ClientConfig(
            cid: 'cid', extCid: 'ext',
            apiKey: 'key', integrationApiKey: 'key',
            timeout: 30,
        );
        $this->assertSame(30, $config->timeout);
    }

    public function testDefaultTimeout(): void
    {
        $config = new ClientConfig(
            cid: 'cid', extCid: 'ext',
            apiKey: 'key', integrationApiKey: 'key',
        );
        $this->assertSame(10, $config->timeout);
    }
}
