<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Cli\Tests;

use SolveBeam\Twinfield\Cli\CredentialsException;
use SolveBeam\Twinfield\Connect\ConnectClient;

final class CredentialStoreTest extends TestCase
{
    public function testDefaultPathIsInTheConfigurationDirectory(): void
    {
        self::assertSame($this->directory . '/credentials.json', $this->store()->path);
    }

    public function testNoProfilesWithoutAFile(): void
    {
        self::assertNull($this->store()->profile('default'));
        self::assertSame([], $this->store()->profiles());
        self::assertFileDoesNotExist($this->store()->path);
    }

    public function testSavesAndReadsProfiles(): void
    {
        $store = $this->store();

        $store->saveProfile('default', self::profile());
        $store->saveProfile('acme', self::profile(organisationCode: 'ACME'));

        self::assertEquals(self::profile(), $store->profile('default'));
        self::assertSame(['default', 'acme'], array_keys($store->profiles()));
        self::assertSame('ACME', $store->profile('acme')?->organisationCode);
    }

    public function testOnlyTheOwnerCanReadTheFile(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('Windows has no file modes.');
        }

        $store = $this->store();

        $store->saveProfile('default', self::profile());

        self::assertSame(0600, fileperms($store->path) & 0777);
        self::assertSame(0700, fileperms($this->directory) & 0777);
    }

    public function testRemovesAProfile(): void
    {
        $store = $this->store();

        $store->saveProfile('default', self::profile());

        self::assertTrue($store->removeProfile('default'));
        self::assertFalse($store->removeProfile('default'));
        self::assertNull($store->profile('default'));
    }

    public function testRemovingDoesNotCreateTheFile(): void
    {
        self::assertFalse($this->store()->removeProfile('default'));
        self::assertFileDoesNotExist($this->store()->path);
    }

    public function testSavesAndReadsAConnectClient(): void
    {
        $store = $this->store();

        self::assertNull($store->connectClient('https://connect.example.com'));

        $store->saveConnectClient(
            'https://connect.example.com',
            new ConnectClient('connect-id', 'connect-secret'),
            'http://localhost:8765/callback',
        );

        $registered = $store->connectClient('https://connect.example.com');

        self::assertNotNull($registered);
        self::assertSame('connect-id', $registered['client']->clientId);
        self::assertSame('connect-secret', $registered['client']->clientSecret);
        self::assertSame('http://localhost:8765/callback', $registered['redirectUri']);
    }

    public function testConnectClientDoesNotOverwriteProfiles(): void
    {
        $store = $this->store();

        $store->saveProfile('default', self::profile());
        $store->saveConnectClient('https://connect.example.com', new ConnectClient('id', 'secret'), 'http://x');

        self::assertEquals(self::profile(), $store->profile('default'));
    }

    public function testRefreshKeepsAValidAccessToken(): void
    {
        $store = $this->store();

        $store->saveProfile('default', self::profile());

        self::assertEquals(self::profile(), $store->refresh('default'));
    }

    public function testRefreshOfAnUnknownProfile(): void
    {
        $this->expectException(CredentialsException::class);
        $this->expectExceptionMessage('vendor/bin/twf login --profile=acme');

        $this->store()->refresh('acme');
    }

    public function testInvalidJson(): void
    {
        mkdir($this->directory);
        file_put_contents($this->directory . '/credentials.json', '{');

        $this->expectException(CredentialsException::class);
        $this->expectExceptionMessage('is not valid JSON');

        $this->store()->profiles();
    }

    public function testAFailedChangeLeavesTheFileAsItWas(): void
    {
        mkdir($this->directory);
        file_put_contents($this->directory . '/credentials.json', '{');

        try {
            $this->store()->saveProfile('default', self::profile());

            self::fail('Expected a CredentialsException.');
        } catch (CredentialsException) {
            self::assertSame('{', file_get_contents($this->directory . '/credentials.json'));
        }
    }
}
