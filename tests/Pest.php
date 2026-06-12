<?php

declare(strict_types=1);

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in(__DIR__.'/Feature');

pest()->extend(Tests\TestCase::class)
    ->in(__DIR__.'/Unit');
