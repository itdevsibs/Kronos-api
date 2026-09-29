<?php

declare(strict_types=1);

namespace Sibs\KronosApi;

final class Environment
{
    public static function get(string $name): ?string
    {
        $processValue = getenv($name);

        if ($processValue !== false) {
            return $processValue;
        }

        if (isset($_ENV[$name])) {
            return (string) $_ENV[$name];
        }

        if (isset($_SERVER[$name])) {
            return (string) $_SERVER[$name];
        }

        return null;
    }
}
