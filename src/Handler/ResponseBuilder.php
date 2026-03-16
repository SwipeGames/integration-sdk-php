<?php

declare(strict_types=1);

namespace SwipeGames\SDK\Handler;

/**
 * Static helpers for building integration adapter responses.
 */
final class ResponseBuilder
{
    /**
     * @return array{balance: string}
     */
    public static function balanceResponse(string $balance): array
    {
        return ['balance' => $balance];
    }

    /**
     * @return array{balance: string, txID: string}
     */
    public static function betResponse(string $balance, string $txID): array
    {
        return ['balance' => $balance, 'txID' => $txID];
    }

    /**
     * @return array{balance: string, txID: string}
     */
    public static function winResponse(string $balance, string $txID): array
    {
        return ['balance' => $balance, 'txID' => $txID];
    }

    /**
     * @return array{balance: string, txID: string}
     */
    public static function refundResponse(string $balance, string $txID): array
    {
        return ['balance' => $balance, 'txID' => $txID];
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
