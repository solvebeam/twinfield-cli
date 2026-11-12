<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Cli;

use RuntimeException;

/**
 * Not logged in, the login failed or expired, or the credentials file cannot be read or written.
 */
final class CredentialsException extends RuntimeException
{
    public static function notLoggedIn(string $profile): self
    {
        return new self(sprintf('Not logged in to Twinfield. Run: %s', self::loginCommand($profile)));
    }

    public static function loginCommand(string $profile): string
    {
        return 'vendor/bin/twinfield login' . ($profile === 'default' ? '' : ' --profile=' . $profile);
    }
}
