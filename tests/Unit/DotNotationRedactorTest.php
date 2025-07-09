<?php

use Prahsys\ApiLogs\Redactors\DotNotationRedactor;

it('redacts simple paths', function () {
    $redactor = new DotNotationRedactor(['request.body.password', 'response.headers.authorization']);

    $data = [
        'request' => [
            'body' => ['username' => 'john', 'password' => 'secret123'],
            'headers' => ['content-type' => 'application/json'],
        ],
        'response' => [
            'body' => ['id' => 123],
            'headers' => ['authorization' => 'Bearer token456'],
        ],
    ];

    $result = $redactor->handle($data, fn ($data) => $data);

    expect($result['request']['body']['password'])->toBe('[REDACTED]');
    expect($result['request']['body']['username'])->toBe('john');
    expect($result['response']['headers']['authorization'])->toBe('[REDACTED]');
    expect($result['response']['body']['id'])->toBe(123);
});

it('redacts wildcard paths', function () {
    $redactor = new DotNotationRedactor(['request.body.*.password']);

    $data = [
        'request' => [
            'body' => [
                'user1' => ['username' => 'john', 'password' => 'secret1'],
                'user2' => ['username' => 'jane', 'password' => 'secret2'],
            ],
        ],
    ];

    $result = $redactor->handle($data, fn ($data) => $data);

    expect($result['request']['body']['user1']['password'])->toBe('[REDACTED]');
    expect($result['request']['body']['user2']['password'])->toBe('[REDACTED]');
    expect($result['request']['body']['user1']['username'])->toBe('john');
    expect($result['request']['body']['user2']['username'])->toBe('jane');
});

it('uses custom replacement', function () {
    $redactor = new DotNotationRedactor(['request.body.token'], '***HIDDEN***');

    $data = [
        'request' => [
            'body' => ['token' => 'abc123'],
        ],
    ];

    $result = $redactor->handle($data, fn ($data) => $data);

    expect($result['request']['body']['token'])->toBe('***HIDDEN***');
});

it('uses callback replacement', function () {
    $redactor = new DotNotationRedactor(
        ['request.body.password'],
        fn ($value, $path, $context) => '['.strlen($value).' chars]'
    );

    $data = [
        'request' => [
            'body' => ['password' => 'secret123'],
        ],
    ];

    $result = $redactor->handle($data, fn ($data) => $data);

    expect($result['request']['body']['password'])->toBe('[9 chars]');
});

it('ignores missing paths', function () {
    $redactor = new DotNotationRedactor(['request.body.nonexistent']);

    $data = [
        'request' => [
            'body' => ['username' => 'john'],
        ],
    ];

    $result = $redactor->handle($data, fn ($data) => $data);

    expect($result['request']['body'])->toBe(['username' => 'john']);
});
