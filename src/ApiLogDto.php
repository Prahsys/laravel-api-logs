<?php

namespace Prahsys\ApiLogs;

class ApiLogDto
{
    public string $id;

    public string $operationName;

    public string $url;

    public bool $success;

    public int $statusCode;

    public string $method;

    public array $request;

    public array $response;

    public array $meta;

    public function __construct(
        string $id,
        string $operationName = '',
        string $url = '',
        bool $success = false,
        int $statusCode = 0,
        string $method = 'GET',
        array $request = ['body' => [], 'headers' => []],
        array $response = ['body' => [], 'headers' => []],
        array $meta = []
    ) {
        $this->id = $id;
        $this->operationName = $operationName;
        $this->url = $url;
        $this->success = $success;
        $this->statusCode = $statusCode;
        $this->method = $method;
        $this->request = $request;
        $this->response = $response;
        $this->meta = $meta;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'operationName' => $this->operationName,
            'url' => $this->url,
            'success' => $this->success,
            'statusCode' => $this->statusCode,
            'method' => $this->method,
            'request' => $this->request,
            'response' => $this->response,
            'meta' => $this->meta,
        ];
    }
}
