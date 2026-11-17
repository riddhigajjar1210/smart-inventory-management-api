<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A business-rule violation (not a validation failure) that must surface as
 * HTTP 422 with a plain-English message.
 */
class InsufficientStockException extends RuntimeException
{
    public static function forAdjustment(int $requested, int $available): self
    {
        return new self(sprintf(
            'Insufficient available stock: %d unit(s) requested but only %d unit(s) are available.',
            $requested,
            $available,
        ));
    }

    public static function forReservationRelease(int $requested, int $reserved): self
    {
        return new self(sprintf(
            'Cannot release %d reserved unit(s); only %d unit(s) are currently reserved.',
            $requested,
            $reserved,
        ));
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [
                'quantity' => [$this->getMessage()],
            ],
        ], 422);
    }
}
