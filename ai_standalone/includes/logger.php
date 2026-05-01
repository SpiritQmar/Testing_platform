<?php

declare(strict_types=1);

class Logger
{
    private string $logFilePath;
    private string $minimumLogLevel;
    private array $logLevels = [
        'DEBUG' => 0,
        'INFO' => 1,
        'WARNING' => 2,
        'ERROR' => 3,
        'CRITICAL' => 4,
    ];

    public function __construct(string $logFilePath = null, string $minimumLogLevel = 'INFO')
    {
        $this->logFilePath = $logFilePath ?? __DIR__ . '/../logs/app.log';
        $this->minimumLogLevel = strtoupper($minimumLogLevel);

        $logDirectory = dirname($this->logFilePath);
        if (!is_dir($logDirectory)) {
            mkdir($logDirectory, 0755, true);
        }
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log('CRITICAL', $message, $context);
    }

    private function log(string $level, string $message, array $context = []): void
    {
        if (!$this->shouldLog($level)) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $requestId = $this->getRequestId();
        $contextJson = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : '';

        $logEntry = sprintf(
            "[%s] [%s] [%s] %s %s\n",
            $timestamp,
            $level,
            $requestId,
            $message,
            $contextJson
        );

        file_put_contents($this->logFilePath, $logEntry, FILE_APPEND | LOCK_EX);

        if (in_array($level, ['ERROR', 'CRITICAL'])) {
            error_log($logEntry);
        }
    }

    private function shouldLog(string $level): bool
    {
        $currentLevelValue = $this->logLevels[$level] ?? 0;
        $minimumLevelValue = $this->logLevels[$this->minimumLogLevel] ?? 0;

        return $currentLevelValue >= $minimumLevelValue;
    }

    private function getRequestId(): string
    {
        static $requestId = null;

        if ($requestId === null) {
            $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? uniqid('req_', true);
        }

        return $requestId;
    }
}

function getLogger(): Logger
{
    static $logger = null;

    if ($logger === null) {
        $config = require __DIR__ . '/../config.php';
        $logLevel = $config['app']['debug'] ? 'DEBUG' : 'INFO';
        $logger = new Logger(null, $logLevel);
    }

    return $logger;
}
