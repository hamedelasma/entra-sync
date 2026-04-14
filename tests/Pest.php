<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use HamedElasma\EntraSync\Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');
