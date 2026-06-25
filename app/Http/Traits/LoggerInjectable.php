<?php

namespace App\Http\Traits;

use Psr\Log\LoggerInterface;

trait LoggerInjectable
{
    protected ?LoggerInterface $logger = null;

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    protected function logInfo(string $message, array $context = []): void
    {
        $this->logger?->info($message, $context);
    }

    protected function logError(string $message, array $context = []): void
    {
        $this->logger?->error($message, $context);
    }

    protected function logWarning(string $message, array $context = []): void
    {
        $this->logger?->warning($message, $context);
    }
}
