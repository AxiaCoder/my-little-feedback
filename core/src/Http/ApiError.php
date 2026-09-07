<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The two error envelopes every API route answers with (spec 01 §3.3).
 *
 * Shared rather than written per controller, because a caller with one error
 * shape to parse writes less code — which is the same argument that makes an
 * unknown product slug a 422 on the field instead of a 404.
 */
final class ApiError
{
    /**
     * The body is not valid JSON, or is not an object. Malformed rather than
     * rejected, so it never reaches the validator.
     */
    public static function malformedJson(): JsonResponse
    {
        return new JsonResponse(
            ['error' => 'malformed_json', 'message' => 'Request body is not valid JSON.'],
            Response::HTTP_BAD_REQUEST,
        );
    }

    /**
     * Every violation is reported, never just the first: a caller fixing one
     * field at a time across five round-trips is a bad API.
     *
     * Sorted by field, so the same broken payload always produces the same
     * response. The order carries no meaning and callers should not read one
     * into it, but an order that changes between runs is worse than one that
     * does not.
     *
     * @param list<array{field: string, message: string}> $violations
     */
    public static function validationFailed(array $violations): JsonResponse
    {
        usort($violations, static fn (array $a, array $b): int => strcmp($a['field'], $b['field']));

        return new JsonResponse(
            ['error' => 'validation_failed', 'violations' => $violations],
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }
}
