<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Cli\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use SolveBeam\Twinfield\Cli\CredentialStore;
use SolveBeam\Twinfield\Cli\Profile;

/**
 * Gives each test its own, empty configuration directory and a clean environment.
 */
abstract class TestCase extends BaseTestCase
{
    private const array VARIABLES = [
        'TWINFIELD_PROFILE',
        'TWINFIELD_REFRESH_TOKEN',
        'TWINFIELD_CLIENT_ID',
        'TWINFIELD_CLIENT_SECRET',
        'TWINFIELD_CONNECT_CLIENT_ID',
        'TWINFIELD_CONNECT_CLIENT_SECRET',
        'TWINFIELD_CONNECT_URL',
        'TWINFIELD_REDIRECT_URI',
    ];

    protected string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/twinfield-cli-' . bin2hex(random_bytes(8));

        foreach (self::VARIABLES as $variable) {
            putenv($variable);
        }

        putenv('TWINFIELD_CONFIG_DIR=' . $this->directory);
    }

    protected function tearDown(): void
    {
        putenv('TWINFIELD_CONFIG_DIR');

        foreach (self::VARIABLES as $variable) {
            putenv($variable);
        }

        if (is_file($this->directory . '/credentials.json')) {
            unlink($this->directory . '/credentials.json');
        }

        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    protected function store(): CredentialStore
    {
        return CredentialStore::default();
    }

    protected static function profile(?int $expires = null, string $organisationCode = 'EXAMPLE'): Profile
    {
        return new Profile(
            'client-id',
            'client-secret',
            'http://localhost:8765/callback',
            null,
            'refresh-token',
            '0b6a5f6e-1111-2222-3333-444455556666',
            $organisationCode,
            'JDOE',
            'https://api.accounting.twinfield.com',
            'access-token',
            $expires ?? time() + 3600,
        );
    }
}
