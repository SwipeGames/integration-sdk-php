<?php

declare(strict_types=1);

namespace SwipeGames\SDK\Handler;

use SwipeGames\PublicApi\Integration\BalanceResponse;
use SwipeGames\PublicApi\Integration\BetResponse;
use SwipeGames\PublicApi\Integration\WinResponse;
use SwipeGames\PublicApi\Integration\RefundResponse;
use SwipeGames\PublicApi\Integration\ErrorResponseWithCodeAndAction;

/**
 * Static helpers for building integration adapter responses.
 */
final class ResponseBuilder
{
    public static function balanceResponse(string $balance): BalanceResponse
    {
        $resp = new BalanceResponse();
        $resp->setBalance($balance);
        return $resp;
    }

    public static function betResponse(string $balance, string $txID): BetResponse
    {
        $resp = new BetResponse();
        $resp->setBalance($balance);
        $resp->setTxId($txID);
        return $resp;
    }

    public static function winResponse(string $balance, string $txID): WinResponse
    {
        $resp = new WinResponse();
        $resp->setBalance($balance);
        $resp->setTxId($txID);
        return $resp;
    }

    public static function refundResponse(string $balance, string $txID): RefundResponse
    {
        $resp = new RefundResponse();
        $resp->setBalance($balance);
        $resp->setTxId($txID);
        return $resp;
    }

    /**
     * Build an error response, omitting null optional fields.
     *
     * @return array<string, string>
     */
    public static function errorResponse(
        string $message,
        ?string $code = null,
        ?string $action = null,
        ?string $actionData = null,
        ?string $details = null,
    ): array {
        $res = ['message' => $message];
        if ($code !== null) {
            $res['code'] = $code;
        }
        if ($action !== null) {
            $res['action'] = $action;
        }
        if ($actionData !== null) {
            $res['actionData'] = $actionData;
        }
        if ($details !== null) {
            $res['details'] = $details;
        }
        return $res;
    }
}
