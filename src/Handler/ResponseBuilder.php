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
     * Build an error response with optional code and action fields.
     */
    public static function errorResponse(
        string $message,
        ?string $code = null,
        ?string $action = null,
        ?string $actionData = null,
        ?string $details = null,
    ): ErrorResponseWithCodeAndAction {
        $resp = new ErrorResponseWithCodeAndAction();
        $resp->setMessage($message);
        if ($code !== null) {
            $resp->setCode($code);
        }
        if ($action !== null) {
            $resp->setAction($action);
        }
        if ($actionData !== null) {
            $resp->setActionData($actionData);
        }
        if ($details !== null) {
            $resp->setDetails($details);
        }
        return $resp;
    }
}
