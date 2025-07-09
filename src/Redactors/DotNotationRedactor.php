<?php

namespace Prahsys\ApiLogs\Redactors;

use Prahsys\ApiLogs\Contracts\RedactorInterface;

class DotNotationRedactor implements RedactorInterface
{
    protected array $sensitivePaths;

    protected string|\Closure $replacement;

    public function __construct(array $sensitivePaths, string|\Closure $replacement = '[REDACTED]')
    {
        $this->sensitivePaths = $sensitivePaths;
        $this->replacement = $replacement;
    }

    public function handle(mixed $data, \Closure $next): mixed
    {
        $redactedData = $this->redactData($data);

        return $next($redactedData);
    }

    protected function redactData(mixed $data): mixed
    {
        if (! is_array($data)) {
            return $data;
        }

        $result = $data;

        foreach ($this->sensitivePaths as $path) {
            if ($this->hasWildcard($path)) {
                $result = $this->redactWildcardPath($result, $path);
            } else {
                $value = data_get($result, $path);
                if ($value !== null) {
                    $redactedValue = $this->getReplacementValue($value, $path, $result);
                    data_set($result, $path, $redactedValue);
                }
            }
        }

        return $result;
    }

    protected function hasWildcard(string $path): bool
    {
        return str_contains($path, '*');
    }

    protected function redactWildcardPath(array $data, string $path): array
    {
        $result = $data;
        $pathParts = explode('.', $path);

        $this->redactWildcardRecursive($result, $pathParts, []);

        return $result;
    }

    protected function redactWildcardRecursive(array &$data, array $pathParts, array $currentPath): void
    {
        if (empty($pathParts)) {
            return;
        }

        $part = array_shift($pathParts);

        if ($part === '*') {
            // Get the current data level to iterate over
            $currentData = data_get($data, implode('.', $currentPath));
            if (! is_array($currentData)) {
                return;
            }

            foreach ($currentData as $key => $value) {
                $newPath = array_merge($currentPath, [$key]);

                if (empty($pathParts)) {
                    // We're at the end of the path, redact this value
                    $fullPath = implode('.', $newPath);
                    $originalValue = data_get($data, $fullPath);
                    $redactedValue = $this->getReplacementValue($originalValue, $fullPath, $data);
                    data_set($data, $fullPath, $redactedValue);
                } else {
                    // Continue recursing with remaining path parts
                    $this->redactWildcardRecursive($data, $pathParts, $newPath);
                }
            }
        } else {
            $newPath = array_merge($currentPath, [$part]);

            if (empty($pathParts)) {
                // We're at the end of the path, redact this value
                $fullPath = implode('.', $newPath);
                $originalValue = data_get($data, $fullPath);
                if ($originalValue !== null) {
                    $redactedValue = $this->getReplacementValue($originalValue, $fullPath, $data);
                    data_set($data, $fullPath, $redactedValue);
                }
            } else {
                // Continue recursing with remaining path parts
                $this->redactWildcardRecursive($data, $pathParts, $newPath);
            }
        }
    }

    /**
     * Get the replacement value for a sensitive field.
     *
     * @param  mixed  $value  The original value being redacted
     * @param  string  $path  The dot notation path to the value
     * @param  array  $context  The full data context
     */
    protected function getReplacementValue(mixed $value, string $path, array $context): mixed
    {
        if ($this->replacement instanceof \Closure) {
            return call_user_func($this->replacement, $value, $path, $context);
        }

        return $this->replacement;
    }
}
