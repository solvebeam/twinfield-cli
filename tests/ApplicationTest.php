<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Cli\Tests;

use SolveBeam\Twinfield\Cli\Application;

final class ApplicationTest extends TestCase
{
    public function testHelpWithoutACommand(): void
    {
        [$status, $output, $error] = $this->twinfield();

        self::assertSame(0, $status);
        self::assertStringContainsString('twinfield login', $output);
        self::assertSame('', $error);
    }

    public function testHelpOption(): void
    {
        [$status, $output] = $this->twinfield('status', '--help');

        self::assertSame(0, $status);
        self::assertStringContainsString('Usage:', $output);
    }

    public function testUnknownCommand(): void
    {
        [$status, $output, $error] = $this->twinfield('foo');

        self::assertSame(1, $status);
        self::assertSame('', $output);
        self::assertStringContainsString('Unknown command "foo"', $error);
    }

    public function testUnknownOption(): void
    {
        [$status, , $error] = $this->twinfield('status', '--foo');

        self::assertSame(1, $status);
        self::assertStringContainsString('Unknown option "--foo"', $error);
    }

    public function testOptionWithoutAValue(): void
    {
        [$status, , $error] = $this->twinfield('token', '--profile');

        self::assertSame(1, $status);
        self::assertStringContainsString('Option --profile needs a value', $error);
    }

    public function testStatusWhenNotLoggedIn(): void
    {
        [$status, , $error] = $this->twinfield('status');

        self::assertSame(1, $status);
        self::assertStringContainsString('Not logged in to Twinfield', $error);
    }

    public function testStatusShowsTheProfiles(): void
    {
        $this->store()->saveProfile('default', self::profile());
        $this->store()->saveProfile('acme', self::profile(organisationCode: 'ACME'));

        [$status, $output] = $this->twinfield('status');

        self::assertSame(0, $status);
        self::assertStringContainsString('default (in use)', $output);
        self::assertStringContainsString('Organisation:  ACME', $output);
        self::assertStringContainsString('valid until', $output);
    }

    public function testStatusOfOneProfile(): void
    {
        $this->store()->saveProfile('default', self::profile());
        $this->store()->saveProfile('acme', self::profile(organisationCode: 'ACME'));

        [$status, $output] = $this->twinfield('status', '--profile=acme');

        self::assertSame(0, $status);
        self::assertStringNotContainsString('EXAMPLE', $output);
    }

    public function testTokenPrintsTheAccessToken(): void
    {
        $this->store()->saveProfile('default', self::profile());

        [$status, $output] = $this->twinfield('token');

        self::assertSame(0, $status);
        self::assertSame('access-token' . PHP_EOL, $output);
    }

    public function testLogout(): void
    {
        $this->store()->saveProfile('acme', self::profile());

        [$status, $output] = $this->twinfield('logout', '--profile=acme');

        self::assertSame(0, $status);
        self::assertStringContainsString('profile "acme"', $output);
        self::assertNull($this->store()->profile('acme'));

        [$status, , $error] = $this->twinfield('logout', '--profile=acme');

        self::assertSame(1, $status);
        self::assertStringContainsString('login --profile=acme', $error);
    }

    public function testLoginRefusesASecretWithoutAClientId(): void
    {
        [$status, , $error] = $this->twinfield('login', '--client-secret=secret');

        self::assertSame(1, $status);
        self::assertStringContainsString('--client-id', $error);
    }

    public function testManualLoginNamesTheRedirectUriAndChecksTheState(): void
    {
        [$status, $output, $error] = $this->twinfieldWithInput(
            'http://localhost:9000/callback?code=abc&state=not-the-state' . PHP_EOL,
            'login',
            '--manual',
            '--client-id=client-id',
            '--client-secret=client-secret',
            '--redirect-uri=http://localhost:9000/callback',
        );

        self::assertSame(1, $status);
        self::assertStringContainsString('Open the Twinfield login in your browser: https://', $output);
        self::assertStringContainsString('your browser goes to http://localhost:9000/callback', $output);
        self::assertStringContainsString('does not belong to this login', $error);
        self::assertNull($this->store()->profile('default'));
    }

    public function testManualLoginReportsTheErrorOfTwinfield(): void
    {
        [$status, , $error] = $this->twinfieldWithInput(
            'http://localhost:8765/callback?error=access_denied&error_description=No' . PHP_EOL,
            'login',
            '--manual',
            '--client-id=client-id',
            '--client-secret=client-secret',
        );

        self::assertSame(1, $status);
        self::assertStringContainsString('Twinfield returned "access_denied": No', $error);
    }

    /**
     * @return array{int, string, string}
     */
    private function twinfield(string ...$arguments): array
    {
        return $this->twinfieldWithInput('', ...$arguments);
    }

    /**
     * @return array{int, string, string} Exit status, standard output and standard error.
     */
    private function twinfieldWithInput(string $input, string ...$arguments): array
    {
        $streams = [];

        foreach (['input', 'output', 'error'] as $name) {
            $stream = fopen('php://memory', 'w+');

            self::assertIsResource($stream);

            $streams[$name] = $stream;
        }

        fwrite($streams['input'], $input);
        rewind($streams['input']);

        $application = new Application($streams['input'], $streams['output'], $streams['error'], $this->store());

        $status = $application->run(['twinfield', ...array_values($arguments)]);

        rewind($streams['output']);
        rewind($streams['error']);

        return [
            $status,
            (string) stream_get_contents($streams['output']),
            (string) stream_get_contents($streams['error']),
        ];
    }
}
