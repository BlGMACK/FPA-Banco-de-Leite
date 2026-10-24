<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function requireIsolatedDatabase(): void
    {
        $connection = config('database.default');
        $configuration = config('database.connections.'.$connection, []);
        $inMemory = $connection === 'sqlite' && ($configuration['database'] ?? null) === ':memory:';
        $isolatedMysql = $connection === 'mysql'
            && ($configuration['database'] ?? null) === 'banco_de_leite_schema_test'
            && in_array($configuration['host'] ?? null, ['127.0.0.1', 'localhost'], true);

        if (! app()->environment('testing') || app()->configurationIsCached()
            || ! empty($configuration['url']) || (! $inMemory && ! $isolatedMysql)) {
            throw new \RuntimeException('Tests require SQLite :memory: or local banco_de_leite_schema_test in testing without cached configuration or a database URL.');
        }
    }
}
