<?php

declare(strict_types=1);

use HamedElasma\EntraSync\Tests\Fixtures\User;

it('scopes active users', function () {
    User::factory()->create(['is_active' => true]);
    User::factory()->create(['is_active' => false]);

    expect(User::active()->count())->toBe(1);
});

it('scopes entra users', function () {
    User::factory()->entra()->create();
    User::factory()->create(['microsoft_id' => null]);

    expect(User::fromEntra()->count())->toBe(1);
});

it('deactivates a user', function () {
    $user = User::factory()->create(['is_active' => true]);

    $user->deactivate();

    expect($user->fresh()->is_active)->toBeFalse();
});

it('checks if user is entra user', function () {
    $entraUser = User::factory()->entra()->create();
    $localUser = User::factory()->create(['microsoft_id' => null]);

    expect($entraUser->isEntraUser())->toBeTrue();
    expect($localUser->isEntraUser())->toBeFalse();
});
