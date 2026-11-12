<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Cli;

/**
 * @internal
 */
final class Env
{
    /**
     * The value of an environment variable, or null when it is not set or empty.
     */
    public static function get(string $name): ?string
    {
        $value = getenv($name);

        return $value === false || $value === '' ? null : $value;
    }
}
