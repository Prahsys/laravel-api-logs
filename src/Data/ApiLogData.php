<?php

namespace Prahsys\ApiLogs\Data;

use Spatie\LaravelData\Data;

class ApiLogData extends Data
{
    public function __construct(
        public string $id,
        public string $operationName = '',
        public string $url = '',
        public bool $success = false,
        public int $statusCode = 0,
        public string $method = 'GET',
        public array $request = ['body' => [], 'headers' => []],
        public array $response = ['body' => [], 'headers' => []],
        public array $meta = []
    ) {}

    /**
     * Get a new instance using the configured data class.
     */
    public static function instance(array $data = []): static
    {
        $dataClass = config('prahsys-api-logs.data.api_log_data', static::class);

        if (! empty($data)) {
            return $dataClass::from($data);
        }

        return new $dataClass('', '', '', false, 0, 'GET', [], [], []);
    }
}
