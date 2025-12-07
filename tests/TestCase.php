<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }
    
    protected function getConfigPath(): string
    {
        return __DIR__ . '/../configs/';
    }
    
    protected function loadConfig(string $configName): array
    {
        $configPath = $this->getConfigPath() . $configName . '.php';
        if (!file_exists($configPath)) {
            throw new \Exception("Config file not found: {$configPath}");
        }
        
        return require $configPath;
    }
}