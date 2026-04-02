<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use O3\EntraSync\Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');
