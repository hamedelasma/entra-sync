<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use HamedElasma\EntraSync\Tests\Fixtures\User;

function fakeTokenResponse(): void
{
    Http::fake([
        'login.microsoftonline.com/*' => Http::response([
            'access_token' => 'fake-token',
            'expires_in' => 3600,
        ]),
    ]);
}

function fakeGraphResponse(array $users, ?string $nextLink = null): array
{
    $response = ['value' => $users];

    if ($nextLink) {
        $response['@odata.nextLink'] = $nextLink;
    }

    return $response;
}

function makeEntraUser(array $overrides = []): array
{
    return array_merge([
        'id' => fake()->uuid(),
        'displayName' => fake()->name(),
        'mail' => fake()->unique()->safeEmail(),
        'userPrincipalName' => fake()->unique()->safeEmail(),
        'department' => 'Engineering',
        'jobTitle' => 'Developer',
        'mobilePhone' => fake()->phoneNumber(),
        'accountEnabled' => true,
    ], $overrides);
}

it('creates new users from Entra response', function () {
    $entraUser = makeEntraUser();

    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]),
        'graph.microsoft.com/*' => Http::response(fakeGraphResponse([$entraUser])),
    ]);

    $this->artisan('users:sync-from-entra')
        ->assertSuccessful();

    $this->assertDatabaseHas('users', [
        'microsoft_id' => $entraUser['id'],
        'email' => $entraUser['mail'],
        'name' => $entraUser['displayName'],
        'department' => $entraUser['department'],
        'job_title' => $entraUser['jobTitle'],
        'phone' => $entraUser['mobilePhone'],
        'is_active' => true,
    ]);
});

it('updates existing users matched by microsoft_id', function () {
    $microsoftId = fake()->uuid();
    User::factory()->entra($microsoftId)->create(['name' => 'Old Name', 'email' => 'old@example.com']);

    $entraUser = makeEntraUser(['id' => $microsoftId, 'displayName' => 'New Name', 'mail' => 'new@example.com']);

    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]),
        'graph.microsoft.com/*' => Http::response(fakeGraphResponse([$entraUser])),
    ]);

    $this->artisan('users:sync-from-entra')
        ->assertSuccessful();

    $this->assertDatabaseHas('users', [
        'microsoft_id' => $microsoftId,
        'name' => 'New Name',
        'email' => 'new@example.com',
    ]);

    $this->assertDatabaseCount('users', 1);
});

it('updates existing users matched by email fallback', function () {
    $email = 'existing@example.com';
    User::factory()->create(['email' => $email, 'microsoft_id' => null]);

    $entraUser = makeEntraUser(['mail' => $email]);

    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]),
        'graph.microsoft.com/*' => Http::response(fakeGraphResponse([$entraUser])),
    ]);

    $this->artisan('users:sync-from-entra')
        ->assertSuccessful();

    $this->assertDatabaseHas('users', [
        'email' => $email,
        'microsoft_id' => $entraUser['id'],
    ]);

    $this->assertDatabaseCount('users', 1);
});

it('deactivates users not in Entra response', function () {
    $activeUser = User::factory()->entra('entra-123')->create(['is_active' => true]);
    $entraUser = makeEntraUser(); // Different user, so entra-123 should be deactivated

    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]),
        'graph.microsoft.com/*' => Http::response(fakeGraphResponse([$entraUser])),
    ]);

    $this->artisan('users:sync-from-entra')
        ->assertSuccessful();

    expect($activeUser->fresh())
        ->is_active->toBeFalse();
});

it('handles pagination via odata nextLink', function () {
    $user1 = makeEntraUser();
    $user2 = makeEntraUser();

    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]),
        'graph.microsoft.com/v1.0/users*' => Http::sequence()
            ->push(fakeGraphResponse([$user1], 'https://graph.microsoft.com/v1.0/users?$skiptoken=page2'))
            ->push(fakeGraphResponse([$user2])),
    ]);

    $this->artisan('users:sync-from-entra')
        ->assertSuccessful();

    $this->assertDatabaseCount('users', 2);
    $this->assertDatabaseHas('users', ['microsoft_id' => $user1['id']]);
    $this->assertDatabaseHas('users', ['microsoft_id' => $user2['id']]);
});

it('does not modify database in dry-run mode', function () {
    $entraUser = makeEntraUser();
    $existingUser = User::factory()->entra('old-entra-id')->create(['is_active' => true]);

    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]),
        'graph.microsoft.com/*' => Http::response(fakeGraphResponse([$entraUser])),
    ]);

    $this->artisan('users:sync-from-entra', ['--dry-run' => true])
        ->assertSuccessful();

    // No new user created
    $this->assertDatabaseCount('users', 1);

    // Existing user not deactivated
    expect($existingUser->fresh())
        ->is_active->toBeTrue()
        ->microsoft_id->toBe('old-entra-id');
});

it('skips users with no email', function () {
    $entraUser = makeEntraUser(['mail' => null, 'userPrincipalName' => null]);

    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]),
        'graph.microsoft.com/*' => Http::response(fakeGraphResponse([$entraUser])),
    ]);

    $this->artisan('users:sync-from-entra')
        ->assertSuccessful();

    $this->assertDatabaseCount('users', 0);
});

it('falls back to userPrincipalName when mail is null', function () {
    $upn = 'user@tenant.onmicrosoft.com';
    $entraUser = makeEntraUser(['mail' => null, 'userPrincipalName' => $upn]);

    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]),
        'graph.microsoft.com/*' => Http::response(fakeGraphResponse([$entraUser])),
    ]);

    $this->artisan('users:sync-from-entra')
        ->assertSuccessful();

    $this->assertDatabaseHas('users', [
        'email' => $upn,
        'microsoft_id' => $entraUser['id'],
    ]);
});

it('handles Graph API error gracefully', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]),
        'graph.microsoft.com/*' => Http::response(['error' => ['message' => 'Unauthorized']], 401),
    ]);

    $this->artisan('users:sync-from-entra')
        ->assertFailed();
});

it('logs warning for email conflict with different microsoft_id', function () {
    User::factory()->entra('existing-entra-id')->create(['email' => 'conflict@example.com']);

    $entraUser = makeEntraUser(['mail' => 'conflict@example.com', 'id' => 'new-entra-id']);

    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]),
        'graph.microsoft.com/*' => Http::response(fakeGraphResponse([$entraUser])),
    ]);

    $this->artisan('users:sync-from-entra')
        ->assertSuccessful()
        ->expectsOutputToContain('Email conflict');
});

it('uses accountEnabled field from Entra', function () {
    $entraUser = makeEntraUser(['accountEnabled' => false]);

    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]),
        'graph.microsoft.com/*' => Http::response(fakeGraphResponse([$entraUser])),
    ]);

    $this->artisan('users:sync-from-entra')
        ->assertSuccessful();

    $this->assertDatabaseHas('users', [
        'microsoft_id' => $entraUser['id'],
        'is_active' => false,
    ]);
});

it('does not deactivate local-only users without microsoft_id', function () {
    $localUser = User::factory()->create(['microsoft_id' => null, 'is_active' => true]);
    $entraUser = makeEntraUser();

    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]),
        'graph.microsoft.com/*' => Http::response(fakeGraphResponse([$entraUser])),
    ]);

    $this->artisan('users:sync-from-entra')
        ->assertSuccessful();

    expect($localUser->fresh())
        ->is_active->toBeTrue();
});
