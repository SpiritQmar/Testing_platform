<?php

declare(strict_types=1);

final class CoefficientResolver
{
    private PDO $pdo;
    private array $globalCoef;
    private array $overrideCache = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $cfg = require __DIR__ . '/../config.php';
        $this->globalCoef = $cfg['coefficients'] ?? [];
    }

    public function resolve(string $category, string $key, ?string $disciplineName = null, ?string $specialty = null): float
    {
        $scopes = [];
        if ($disciplineName !== null) $scopes[] = ['discipline', $disciplineName];
        if ($specialty !== null)      $scopes[] = ['specialty',  $specialty];

        foreach ($scopes as [$type, $value]) {
            $cacheKey = "$type|$value|$category|$key";
            if (!array_key_exists($cacheKey, $this->overrideCache)) {
                $stmt = $this->pdo->prepare(
                    "SELECT coef_value FROM coefficient_overrides
                     WHERE scope_type = :t AND scope_value = :v AND category = :c AND coef_key = :k LIMIT 1"
                );
                $stmt->execute([':t' => $type, ':v' => $value, ':c' => $category, ':k' => $key]);
                $row = $stmt->fetchColumn();
                $this->overrideCache[$cacheKey] = ($row !== false) ? (float)$row : null;
            }
            if ($this->overrideCache[$cacheKey] !== null) {
                return $this->overrideCache[$cacheKey];
            }
        }

        return (float)($this->globalCoef[$category][$key] ?? 0.0);
    }

    public function listOverrides(): array
    {
        return $this->pdo->query(
            "SELECT * FROM coefficient_overrides ORDER BY scope_type, scope_value, category, coef_key"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function setOverride(string $scopeType, string $scopeValue, string $category, string $key, float $value): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO coefficient_overrides (scope_type, scope_value, category, coef_key, coef_value)
             VALUES (:t, :v, :c, :k, :val)
             ON DUPLICATE KEY UPDATE coef_value = VALUES(coef_value)"
        );
        $stmt->execute([':t' => $scopeType, ':v' => $scopeValue, ':c' => $category, ':k' => $key, ':val' => $value]);
        unset($this->overrideCache["$scopeType|$scopeValue|$category|$key"]);
    }

    public function deleteOverride(int $overrideId): void
    {
        $this->pdo->prepare("DELETE FROM coefficient_overrides WHERE override_id = :id")->execute([':id' => $overrideId]);
        $this->overrideCache = [];
    }
}
