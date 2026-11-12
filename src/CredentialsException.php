<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Cli;

use RuntimeException;

/**
 * Not logged in, the login failed or expired, or the credentials file cannot be read or written.
 */
final class CredentialsException extends RuntimeException
{
    /** The command as the user typed it; outside the command line tool, the one of Composer. */
    private static string $command = 'vendor/bin/twf';

    /**
     * Makes the messages name the command the way it was called, e.g. `vendor/bin/twinfield`.
     *
     * @internal
     *
     * @param string $script The script name, `$argv[0]`.
     */
    public static function calledAs(string $script): void
    {
        if ($script === '') {
            return;
        }

        // A full path means the command was found on the PATH: its name is what the user typed.
        if (preg_match('~^(?:[/\\\\]|[A-Za-z]:[/\\\\]).*?([^/\\\\]+)$~', $script, $matches) === 1) {
            $script = $matches[1];
        }

        self::$command = $script;
    }

    /**
     * The command line of a command, e.g. `vendor/bin/twf status`.
     *
     * @internal
     */
    public static function command(string $arguments): string
    {
        return self::$command . ' ' . $arguments;
    }

    /**
     * The name of the command without its directory: `twf` or `twinfield`.
     *
     * @internal
     */
    public static function commandName(): string
    {
        return basename(self::$command);
    }

    public static function notLoggedIn(string $profile): self
    {
        return new self(sprintf('Not logged in to Twinfield. Run: %s', self::loginCommand($profile)));
    }

    public static function loginCommand(string $profile): string
    {
        return self::command('login' . ($profile === 'default' ? '' : ' --profile=' . $profile));
    }
}
