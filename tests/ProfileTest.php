<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Cli\Tests;

use SolveBeam\Twinfield\Cli\CredentialsException;
use SolveBeam\Twinfield\Cli\Profile;

final class ProfileTest extends TestCase
{
    public function testArrayRoundTrip(): void
    {
        $profile = self::profile();

        self::assertEquals($profile, Profile::fromArray($profile->toArray()));
    }

    public function testFromArrayNamesTheMissingField(): void
    {
        $data = self::profile()->toArray();

        unset($data['refresh_token']);

        $this->expectException(CredentialsException::class);
        $this->expectExceptionMessage('"refresh_token"');

        Profile::fromArray($data);
    }

    public function testAccessTokenIsNotValidShortlyBeforeItExpires(): void
    {
        self::assertTrue(self::profile(time() + 3600)->hasValidAccessToken());
        self::assertFalse(self::profile(time() + 30)->hasValidAccessToken());
        self::assertFalse(self::profile(time() - 1)->hasValidAccessToken());
    }

    public function testUsesConnectOnlyWithAConnectUrl(): void
    {
        $data = self::profile()->toArray();

        self::assertFalse(Profile::fromArray($data)->usesConnect());

        $data['connect_url'] = 'https://connect.example.com';

        self::assertTrue(Profile::fromArray($data)->usesConnect());
    }
}
