<?php

declare(strict_types=1);

function loadEnvironmentVariables(string $envFilePath): void
{
    if (!file_exists($envFilePath)) {
        return;
    }

    $lines = file($envFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if (empty($line) || str_starts_with($line, '#')) {
            continue;
        }

        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            $value = trim($value, '"\'');

            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
}

function getEnvironmentVariable(string $key, mixed $defaultValue = null): mixed
{
    return $_ENV[$key] ?? getenv($key) ?: $defaultValue;
}
