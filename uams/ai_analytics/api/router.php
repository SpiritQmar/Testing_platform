<?php

declare(strict_types=1);

class AnalyticsServiceRouter
{
    private array $appConfig;
    private PDO $databaseConnection;

    public function __construct(PDO $databaseConnection)
    {
        $this->databaseConnection = $databaseConnection;
        $this->appConfig = require __DIR__ . '/../config.php';
    }

    public function executeServiceMethod(string $serviceName, string $methodName, array $methodParameters = []): array
    {
        if ($this->appConfig['containers']['enabled']) {
            $containerResult = $this->executeContainerService($serviceName, $methodName, $methodParameters);
            if ($containerResult !== null) {
                return $containerResult;
            }
        }

        return $this->executeLocalService($serviceName, $methodName, $methodParameters);
    }

    private function executeContainerService(string $serviceName, string $methodName, array $methodParameters): ?array
    {
        $serviceConfiguration = $this->appConfig['containers']['services'][$serviceName] ?? null;
        if (!$serviceConfiguration || !$serviceConfiguration['enabled']) {
            return null;
        }

        $containerEndpointUrl = sprintf(
            '%s:%d%s/%s',
            $this->appConfig['containers']['base_url'],
            $serviceConfiguration['port'],
            $serviceConfiguration['endpoint'],
            $methodName
        );

        try {
            $curlHandle = curl_init($containerEndpointUrl);
            curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curlHandle, CURLOPT_POST, true);
            curl_setopt($curlHandle, CURLOPT_POSTFIELDS, json_encode($methodParameters));
            curl_setopt($curlHandle, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($curlHandle, CURLOPT_TIMEOUT, $this->appConfig['api']['timeout']);

            $apiResponse = curl_exec($curlHandle);
            $httpStatusCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
            curl_close($curlHandle);

            if ($httpStatusCode === 200 && $apiResponse) {
                return json_decode($apiResponse, true);
            }
        } catch (Throwable $exception) {
            error_log('Ошибка при обращении к контейнеру ' . $serviceName . ': ' . $exception->getMessage());
        }

        return null;
    }

    private function executeLocalService(string $serviceName, string $methodName, array $methodParameters): array
    {
        $serviceClassName = ucfirst($serviceName) . 'Service';
        $serviceFilePath = __DIR__ . '/../services/' . $serviceClassName . '.php';

        if (!file_exists($serviceFilePath)) {
            return [
                'error' => 'Сервис аналитики ' . $serviceName . ' не найден. Проверьте наличие файла ' . $serviceFilePath
            ];
        }

        require_once $serviceFilePath;

        try {
            $serviceInstance = new $serviceClassName($this->databaseConnection);

            if (!method_exists($serviceInstance, $methodName)) {
                return [
                    'error' => 'Метод ' . $methodName . ' не найден в сервисе ' . $serviceClassName
                ];
            }

            $executionResult = $serviceInstance->$methodName(...array_values($methodParameters));
            return is_array($executionResult) ? $executionResult : ['result' => $executionResult];

        } catch (Throwable $exception) {
            error_log('Ошибка выполнения сервиса ' . $serviceClassName . '::' . $methodName . ': ' . $exception->getMessage());
            return [
                'error' => 'Ошибка при выполнении: ' . $exception->getMessage(),
                'service' => $serviceName,
                'method' => $methodName
            ];
        }
    }

    public function getActiveAnalysisModules(): array
    {
        $availableServices = [];

        foreach ($this->appConfig['local']['services'] as $moduleName => $isEnabled) {
            if ($isEnabled) {
                $availableServices[] = [
                    'module_name' => $moduleName,
                    'deployment_type' => 'local_php',
                    'operational_status' => 'active',
                ];
            }
        }

        if ($this->appConfig['containers']['enabled']) {
            foreach ($this->appConfig['containers']['services'] as $moduleName => $moduleConfig) {
                $moduleStatus = $this->verifyContainerHealth($moduleName, $moduleConfig);
                $availableServices[] = [
                    'module_name' => $moduleName,
                    'deployment_type' => 'docker_container',
                    'container_port' => $moduleConfig['port'],
                    'operational_status' => $moduleStatus,
                ];
            }
        }

        return $availableServices;
    }

    private function verifyContainerHealth(string $moduleName, array $moduleConfig): string
    {
        if (!$moduleConfig['enabled']) return 'disabled';

        $healthCheckUrl = sprintf('%s:%d/health', $this->appConfig['containers']['base_url'], $moduleConfig['port']);

        try {
            $curlHandle = curl_init($healthCheckUrl);
            curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curlHandle, CURLOPT_TIMEOUT, 5);
            $healthCheckResponse = curl_exec($curlHandle);
            $httpStatusCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
            curl_close($curlHandle);

            return $httpStatusCode === 200 ? 'running' : 'unavailable';
        } catch (Throwable $exception) {
            error_log('Не удалось проверить здоровье контейнера ' . $moduleName . ': ' . $exception->getMessage());
            return 'unavailable';
        }
    }

    public function updateAnalysisCoefficients(array $updatedCoefficients): bool
    {
        $configurationFilePath = __DIR__ . '/../config.php';
        $currentConfiguration = require $configurationFilePath;

        $currentConfiguration['coefficients'] = array_replace_recursive(
            $currentConfiguration['coefficients'],
            $updatedCoefficients
        );

        $updatedConfigContent = "<?php\nreturn " . var_export($currentConfiguration, true) . ";\n";
        $writeResult = file_put_contents($configurationFilePath, $updatedConfigContent);

        if ($writeResult === false) {
            error_log('Не удалось сохранить обновленные коэффициенты в ' . $configurationFilePath);
            return false;
        }

        return true;
    }
}
