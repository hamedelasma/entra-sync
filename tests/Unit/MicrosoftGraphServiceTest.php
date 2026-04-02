<?php

declare(strict_types=1);

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use O3\EntraSync\Services\MicrosoftGraphService;

beforeEach(function () {
    $this->service = new MicrosoftGraphService(
        tenantId: 'test-tenant',
        clientId: 'test-client',
        clientSecret: 'test-secret',
    );
});

it('fetches users from Graph API', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
        'graph.microsoft.com/*' => Http::response([
            'value' => [
                ['id' => '1', 'displayName' => 'User One', 'mail' => 'one@example.com'],
                ['id' => '2', 'displayName' => 'User Two', 'mail' => 'two@example.com'],
            ],
        ]),
    ]);

    $users = $this->service->fetchAllUsers();

    expect($users)->toHaveCount(2);
    expect($users[0]['displayName'])->toBe('User One');
});

it('handles pagination', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
        'graph.microsoft.com/*' => Http::sequence()
            ->push([
                'value' => [['id' => '1', 'displayName' => 'User One']],
                '@odata.nextLink' => 'https://graph.microsoft.com/v1.0/users?$skiptoken=page2',
            ])
            ->push([
                'value' => [['id' => '2', 'displayName' => 'User Two']],
            ]),
    ]);

    $users = $this->service->fetchAllUsers();

    expect($users)->toHaveCount(2);
});

it('caches the access token', function () {
    Cache::flush();

    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'cached-token', 'expires_in' => 3600]),
        'graph.microsoft.com/*' => Http::response(['value' => []]),
    ]);

    $this->service->fetchAllUsers();
    $this->service->fetchAllUsers();

    // Token endpoint should only be called once due to caching
    Http::assertSentCount(3); // 1 token + 2 graph requests
});

it('throws on Graph API error', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
        'graph.microsoft.com/*' => Http::response(['error' => 'Forbidden'], 403),
    ]);

    $this->service->fetchAllUsers();
})->throws(RequestException::class);

it('throws on token error', function () {
    Cache::flush();

    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['error' => 'invalid_client'], 400),
    ]);

    $this->service->fetchAllUsers();
})->throws(RequestException::class);
