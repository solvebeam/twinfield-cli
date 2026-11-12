<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Cli\Tests;

use SolveBeam\Twinfield\Authentication\RefreshTokenAccessTokenProvider;
use SolveBeam\Twinfield\Cli\Credentials;
use SolveBeam\Twinfield\Cli\CredentialsException;
use SolveBeam\Twinfield\Cli\ProfileAccessTokenProvider;

final class CredentialsTest extends TestCase
{
    public function testNotLoggedIn(): void
    {
        $this->expectException(CredentialsException::class);
        $this->expectExceptionMessage('Run: vendor/bin/twinfield login');

        Credentials::default();
    }

    public function testUsesTheDefaultProfile(): void
    {
        $this->store()->saveProfile('default', self::profile());

        $credentials = Credentials::default();

        self::assertSame('access-token', $credentials->accessToken());
        self::assertSame('https://api.accounting.twinfield.com', $credentials->clusterUrl());
        self::assertSame('JDOE', $credentials->userCode());
        self::assertSame('EXAMPLE', $credentials->organisation()->code);
    }

    public function testUsesTheProfileOfTheEnvironment(): void
    {
        $this->store()->saveProfile('acme', self::profile(organisationCode: 'ACME'));

        putenv('TWINFIELD_PROFILE=acme');

        self::assertSame('ACME', Credentials::default()->organisation()->code);
    }

    public function testRefreshTokenOfTheEnvironmentGoesBeforeTheFile(): void
    {
        $this->store()->saveProfile('default', self::profile());

        putenv('TWINFIELD_REFRESH_TOKEN=refresh-token');
        putenv('TWINFIELD_CLIENT_ID=client-id');
        putenv('TWINFIELD_CLIENT_SECRET=client-secret');

        self::assertInstanceOf(RefreshTokenAccessTokenProvider::class, Credentials::default());
    }

    public function testANamedProfileGoesBeforeTheRefreshTokenOfTheEnvironment(): void
    {
        $this->store()->saveProfile('acme', self::profile());

        putenv('TWINFIELD_REFRESH_TOKEN=refresh-token');

        self::assertInstanceOf(ProfileAccessTokenProvider::class, Credentials::default('acme'));
    }

    public function testRefreshTokenWithoutAClient(): void
    {
        putenv('TWINFIELD_REFRESH_TOKEN=refresh-token');

        $this->expectException(CredentialsException::class);
        $this->expectExceptionMessage('TWINFIELD_CLIENT_ID');

        Credentials::default();
    }
}
