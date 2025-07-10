<?php

namespace Prahsys\ApiLogs\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Prahsys\ApiLogs\Data\ApiLogData;
use Prahsys\ApiLogs\Events\CompleteIdempotentRequestEvent;
use Prahsys\ApiLogs\Services\IdempotencyService;
use Prahsys\ApiLogs\Services\ModelIdempotencyTracker;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyLogMiddleware
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        protected IdempotencyService $idempotencyService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip if not configured to log this request
        if (! $this->shouldLogRequest($request)) {
            return $next($request);
        }

        // Generate or use existing idempotency key
        $idempotencyHeaderName = config('prahsys-api-logs.idempotency.header_name', 'Idempotency-Key');
        $requestId = $request->header($idempotencyHeaderName) ?? (string) Str::uuid();
        $request->headers->set($idempotencyHeaderName, $requestId);

        // Start building the ApiLogData
        $apiLogData = $this->startApiLogData($request, $requestId);
        $request->attributes->set('api_log_data', $apiLogData);

        // Process the request
        $response = $next($request);

        return $response;
    }

    /**
     * Handle the response after it has been sent to the client.
     */
    public function terminate(Request $request, Response $response): void
    {
        if (! $this->shouldLogRequest($request)) {
            return;
        }

        $apiLogData = $request->attributes->get('api_log_data');
        if (! $apiLogData) {
            return;
        }

        // Complete the ApiLogData with response data
        $this->completeApiLogData($apiLogData, $response);

        // Store idempotent request record in database
        $idempotentRequest = $this->storeIdempotentRequest($apiLogData);

        // Get tracked models and fire event with ApiLogData
        $this->fireCompleteEvent($apiLogData, $idempotentRequest);
    }

    /**
     * Start building the ApiLogData with request data.
     */
    protected function startApiLogData(Request $request, string $requestId): ApiLogData
    {
        $requestData = [
            'headers' =>  array_map(fn($values) => implode(PHP_EOL, $values), $request->headers->all()),
            'body' => $request->all() ?: [],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'api_version' => $request->header('Accept-Version', 'default'),
            'timestamp' => now()->toIso8601String(),
        ];

        return ApiLogData::instance([
            'id' => $requestId,
            'operationName' => $this->getOperationName($request),
            'url' => $request->fullUrl(),
            'success' => false, // Will be updated in completeApiLogData
            'statusCode' => 0, // Will be updated in completeApiLogData
            'method' => $request->method(),
            'request' => $requestData,
            'response' => ['body' => [], 'headers' => []], // Will be updated in completeApiLogData
            'meta' => [], // Available for additional metadata
        ]);
    }

    /**
     * Complete the ApiLogData with response data.
     */
    protected function completeApiLogData(ApiLogData $apiLogData, Response $response): void
    {
        $apiLogData->statusCode = $response->getStatusCode();
        $apiLogData->success = $response->getStatusCode() < 400;
        $apiLogData->response = [
            'headers' => array_map(fn($values) => implode(PHP_EOL, $values), $response->headers->all()),
            'body' => $this->getResponseBody($response),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Store idempotent request record in database.
     */
    protected function storeIdempotentRequest(ApiLogData $apiLogData): mixed
    {
        try {
            $logData = [
                'request_id' => $apiLogData->id,
                'path' => parse_url($apiLogData->url, PHP_URL_PATH),
                'method' => $apiLogData->method,
                'api_version' => $apiLogData->request['api_version'] ?? 'default',
                'request_at' => $apiLogData->request['timestamp'],
                'response_at' => $apiLogData->response['timestamp'],
                'response_status' => $apiLogData->statusCode,
                'is_error' => ! $apiLogData->success,
            ];

            return $this->idempotencyService->storeIdempotentRequest($logData);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to store idempotent request: '.$e->getMessage(), [
                'exception' => $e,
                'request_id' => $apiLogData->id,
            ]);

            return null;
        }
    }

    /**
     * Fire the CompleteIdempotentRequestEvent with tracked models and ApiLogData.
     */
    protected function fireCompleteEvent(ApiLogData $apiLogData, $idempotentRequest): void
    {
        if (! $idempotentRequest) {
            return;
        }

        // Get tracked models
        $tracker = app(ModelIdempotencyTracker::class);
        $models = $tracker->getModelsForRequest($apiLogData->id);

        // Fire event with ApiLogData
        CompleteIdempotentRequestEvent::dispatch(
            $apiLogData->id,
            $idempotentRequest->id,
            $models->toArray(),
            $apiLogData
        );

        // Clear the tracker for this request ID
        $tracker->clearRequest($apiLogData->id);
    }

    /**
     * Determine if the request should be logged.
     */
    protected function shouldLogRequest(Request $request): bool
    {
        // Check if API logging is enabled
        if (! config('prahsys-api-logs.enabled', true)) {
            return false;
        }

        // Skip options requests (CORS preflight)
        if ($request->method() === 'OPTIONS') {
            return false;
        }

        // Check if the path should be excluded
        $excludePaths = config('prahsys-api-logs.exclude_paths', []);
        $path = $request->path();

        foreach ($excludePaths as $excludePath) {
            if (Str::is($excludePath, $path)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get operation name from the request.
     */
    protected function getOperationName(Request $request): string
    {
        // Try to get route name first
        if ($request->route() && $request->route()->getName()) {
            return $request->route()->getName();
        }

        // Fallback to method + path
        return $request->method().' '.$request->path();
    }

    /**
     * Get response body as array or string.
     */
    protected function getResponseBody(Response $response): mixed
    {
        $content = $response->getContent();

        // Try to decode JSON response
        if (str_contains($response->headers->get('Content-Type', ''), 'application/json')) {
            $decoded = json_decode($content, true);

            return $decoded !== null ? $decoded : $content;
        }

        return $content;
    }
}
