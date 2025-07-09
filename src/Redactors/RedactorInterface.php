<?php

namespace Prahsys\ApiLogs\Redactors;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

interface RedactorInterface
{
    /**
     * Redact sensitive information from request headers.
     */
    public function redactHeaders(array $headers): array;

    /**
     * Redact sensitive information from request body.
     */
    public function redactRequestBody(mixed $body): mixed;

    /**
     * Redact sensitive information from response body.
     */
    public function redactResponseBody(mixed $body): mixed;

    /**
     * Create a redacted version of the request data.
     */
    public function redactRequest(Request $request, array $requestData): array;

    /**
     * Create a redacted version of the response data.
     */
    public function redactResponse(Response $response, array $responseData): array;
}
