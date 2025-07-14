<?php

namespace Prahsys\ApiLogs\Http\Middleware;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Str;
use Prahsys\ApiLogs\Data\ApiLogData;
use Prahsys\ApiLogs\Events\CompleteApiLogItemEvent;
use Prahsys\ApiLogs\Services\ApiLogItemService;
use Prahsys\ApiLogs\Services\ApiLogItemTracker;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class GuzzleApiLogMiddleware
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        protected ApiLogItemService $apiLogItemService
    ) {}

    /**
     * Guzzle middleware handler.
     */
    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
            // Skip if not configured to log this request
            if (! $this->shouldLogRequest($request, $options)) {
                return $handler($request, $options);
            }

            // Generate or use existing correlation ID
            $correlationHeaderName = config('api-logs.correlation.header_name', 'Idempotency-Key');
            $ensureHeader = config('api-logs.correlation.ensure_header', true);
            $requestId = $request->getHeaderLine($correlationHeaderName);

            // If header is missing and ensure_header is false, skip logging
            if (! $requestId && ! $ensureHeader) {
                return $handler($request, $options);
            }

            // Auto-generate if missing and ensure_header is true
            if (! $requestId) {
                $requestId = (string) Str::orderedUuid();
                $request = $request->withHeader($correlationHeaderName, $requestId);
            }

            // Start building the ApiLogData
            $apiLogData = $this->startApiLogData($request, $requestId);

            // Process the request and handle response/error
            return $handler($request, $options)->then(
                function (ResponseInterface $response) use ($apiLogData) {
                    // Complete the ApiLogData with response data
                    $this->completeApiLogData($apiLogData, $response);

                    // Store API log item record in database
                    $apiLogItem = $this->storeApiLog($apiLogData);

                    // Fire event with ApiLogData
                    $this->fireCompleteEvent($apiLogData, $apiLogItem);

                    return $response;
                },
                function ($reason) use ($apiLogData) {
                    // Handle request/response errors
                    $this->completeApiLogDataWithError($apiLogData, $reason);

                    // Store API log item record in database
                    $apiLogItem = $this->storeApiLog($apiLogData);

                    // Fire event with ApiLogData
                    $this->fireCompleteEvent($apiLogData, $apiLogItem);

                    throw $reason;
                }
            );
        };
    }

    /**
     * Start building the ApiLogData with request data.
     */
    protected function startApiLogData(RequestInterface $request, string $requestId): ApiLogData
    {
        $requestData = [
            'headers' => $this->formatHeaders($request->getHeaders()),
            'body' => $this->getRequestBody($request),
            'ip_address' => gethostbyname(parse_url((string) $request->getUri(), PHP_URL_HOST) ?: 'localhost'),
            'user_agent' => $request->getHeaderLine('User-Agent') ?: 'Guzzle HTTP Client',
            'api_version' => $request->getHeaderLine('Accept-Version') ?: 'default',
            'timestamp' => now()->toIso8601String(),
        ];

        // Try to get parent request ID from current Laravel request context
        $parentRequestId = $this->getParentRequestId();

        $meta = [
            'type' => 'outbound', // Mark as outbound API call
            'client' => 'guzzle',
        ];

        // Add parent_id if we have one
        if ($parentRequestId) {
            $meta['parent_id'] = $parentRequestId;
        }

        return ApiLogData::instance([
            'id' => $requestId,
            'operationName' => $this->getOperationName($request),
            'url' => (string) $request->getUri(),
            'success' => false, // Will be updated in completeApiLogData
            'statusCode' => 0, // Will be updated in completeApiLogData
            'method' => $request->getMethod(),
            'request' => $requestData,
            'response' => ['body' => [], 'headers' => []], // Will be updated in completeApiLogData
            'meta' => $meta,
        ]);
    }

    /**
     * Complete the ApiLogData with response data.
     */
    protected function completeApiLogData(ApiLogData $apiLogData, ResponseInterface $response): void
    {
        $apiLogData->statusCode = $response->getStatusCode();
        $apiLogData->success = $response->getStatusCode() < 400;
        $apiLogData->response = [
            'headers' => $this->formatHeaders($response->getHeaders()),
            'body' => $this->getResponseBody($response),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Complete the ApiLogData with error information.
     */
    protected function completeApiLogDataWithError(ApiLogData $apiLogData, $reason): void
    {
        $statusCode = 0;
        $responseBody = [];

        // Extract status code from Guzzle exceptions
        if ($reason instanceof \GuzzleHttp\Exception\RequestException && $reason->hasResponse()) {
            $response = $reason->getResponse();
            $statusCode = $response->getStatusCode();
            $responseBody = $this->getResponseBody($response);
        }

        $apiLogData->statusCode = $statusCode ?: 500;
        $apiLogData->success = false;
        $apiLogData->response = [
            'headers' => [],
            'body' => $responseBody ?: ['error' => $reason->getMessage()],
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Store API log item record in database.
     */
    protected function storeApiLog(ApiLogData $apiLogData): mixed
    {
        try {
            $logData = [
                'request_id' => $apiLogData->id,
                'path' => parse_url($apiLogData->url, PHP_URL_PATH) ?: '/',
                'method' => $apiLogData->method,
                'api_version' => $apiLogData->request['api_version'] ?? 'default',
                'request_at' => $apiLogData->request['timestamp'],
                'response_at' => $apiLogData->response['timestamp'],
                'response_status' => $apiLogData->statusCode,
                'is_error' => ! $apiLogData->success,
            ];

            return $this->apiLogItemService->storeApiLogItem($logData);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to store outbound API log item: '.$e->getMessage(), [
                'exception' => $e,
                'request_id' => $apiLogData->id,
            ]);

            return null;
        }
    }

    /**
     * Fire the CompleteApiLogItemEvent with tracked models and ApiLogData.
     */
    protected function fireCompleteEvent(ApiLogData $apiLogData, $apiLogItem): void
    {
        if (! $apiLogItem) {
            return;
        }

        // For outbound calls, we typically don't track models in the same way,
        // but we still fire the event for consistency and extensibility
        $tracker = app(ApiLogItemTracker::class);
        $models = $tracker->getModelsForRequest($apiLogData->id);

        // Fire event with ApiLogData
        CompleteApiLogItemEvent::dispatch(
            $apiLogData->id,
            $apiLogItem->id,
            $models->toArray(),
            $apiLogData
        );

        // Clear the tracker for this request ID
        $tracker->clearRequest($apiLogData->id);
    }

    /**
     * Determine if the request should be logged.
     */
    protected function shouldLogRequest(RequestInterface $request, array $options): bool
    {
        // Check if outbound API logging is enabled
        if (! config('api-logs.outbound.enabled', true)) {
            return false;
        }

        // Check if this specific request should be excluded
        $excludeHosts = config('api-logs.outbound.exclude_hosts', []);
        $host = parse_url((string) $request->getUri(), PHP_URL_HOST);

        foreach ($excludeHosts as $excludeHost) {
            if ($host && fnmatch($excludeHost, $host)) {
                return false;
            }
        }

        // Check if specific request options disable logging
        if (isset($options['prahsys_api_logs_skip']) && $options['prahsys_api_logs_skip']) {
            return false;
        }

        return true;
    }

    /**
     * Get operation name from the request.
     */
    protected function getOperationName(RequestInterface $request): string
    {
        $uri = $request->getUri();
        $path = $uri->getPath();
        $host = $uri->getHost();

        return sprintf('%s %s%s', $request->getMethod(), $host ?: 'unknown', $path ?: '/');
    }

    /**
     * Format headers array for consistent storage.
     */
    protected function formatHeaders(array $headers): array
    {
        return array_map(function ($values) {
            return implode(PHP_EOL, $values);
        }, $headers);
    }

    /**
     * Get request body as array or string.
     */
    protected function getRequestBody(RequestInterface $request): mixed
    {
        $body = (string) $request->getBody();

        if (empty($body)) {
            return [];
        }

        // Try to decode JSON request body
        $contentType = $request->getHeaderLine('Content-Type');
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode($body, true);

            return $decoded !== null ? $decoded : $body;
        }

        return $body;
    }

    /**
     * Get response body as array or string.
     */
    protected function getResponseBody(ResponseInterface $response): mixed
    {
        $body = (string) $response->getBody();

        if (empty($body)) {
            return [];
        }

        // Try to decode JSON response
        $contentType = $response->getHeaderLine('Content-Type');
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode($body, true);

            return $decoded !== null ? $decoded : $body;
        }

        return $body;
    }

    /**
     * Get the parent request ID from the current Laravel request context.
     */
    protected function getParentRequestId(): ?string
    {
        try {
            // Try to get the current Laravel request
            if (app()->bound('request')) {
                $laravelRequest = app('request');

                // Check if we have api_log_data set by the ApiLogMiddleware
                if ($laravelRequest && $laravelRequest->attributes) {
                    $apiLogData = $laravelRequest->attributes->get('apiLogData');
                    if ($apiLogData && isset($apiLogData->id)) {
                        return $apiLogData->id;
                    }
                }

                // Fallback to checking for the correlation header on the Laravel request
                if ($laravelRequest && $laravelRequest->headers) {
                    $correlationHeaderName = config('api-logs.correlation.header_name', 'Idempotency-Key');
                    $headerValue = $laravelRequest->headers->get($correlationHeaderName);
                    if ($headerValue) {
                        return $headerValue;
                    }
                }
            }
        } catch (\Exception $e) {
            // Silently fail if we can't get the parent request ID
            // This might happen in CLI contexts or when Laravel context is not available
        }

        return null;
    }
}
