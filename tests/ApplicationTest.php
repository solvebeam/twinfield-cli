<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Cli\Tests;

use SolveBeam\Twinfield\Cli\Application;
use SolveBeam\Twinfield\Cli\CredentialsException;
use Symfony\Component\Console\Tester\ApplicationTester;

final class ApplicationTest extends TestCase
{
    public function testHelpWithoutACommand(): void
    {
        [$status, $output, $error] = $this->twf();

        self::assertSame(0, $status);
        self::assertStringContainsString('Twinfield CLI brings Twinfield to your terminal.', $output);
        self::assertMatchesRegularExpression('/^  login +Log in with your browser$/m', $output);
        self::assertSame('', $error);
    }

    public function testHelpOptionShowsTheHelpOfTheCommand(): void
    {
        [$status, $output] = $this->twf('login', '--help');

        self::assertSame(0, $status);
        self::assertStringContainsString('login [options]', $output);
        self::assertStringContainsString('--manual', $output);
        self::assertStringContainsString('--profile=PROFILE', $output);
    }

    public function testUnknownCommand(): void
    {
        [$status, $output, $error] = $this->twf('foo');

        self::assertSame(1, $status);
        self::assertSame('', $output);
        self::assertStringContainsString('Command "foo" is not defined.', $error);
    }

    public function testUnknownOption(): void
    {
        [$status, , $error] = $this->twf('status', '--foo');

        self::assertSame(1, $status);
        self::assertStringContainsString('The "--foo" option does not exist.', $error);
    }

    public function testAnEmptyProfileIsTheDefaultProfile(): void
    {
        [$status, , $error] = $this->twf('token', '--profile=');

        self::assertSame(1, $status);
        self::assertStringContainsString('Not logged in to Twinfield. Run: vendor/bin/twf login', $error);
    }

    public function testStatusWhenNotLoggedIn(): void
    {
        [$status, , $error] = $this->twf('status');

        self::assertSame(1, $status);
        self::assertStringContainsString('Not logged in to Twinfield', $error);
    }

    public function testStatusShowsTheProfiles(): void
    {
        $this->store()->saveProfile('default', self::profile());
        $this->store()->saveProfile('acme', self::profile(organisationCode: 'ACME'));

        [$status, $output] = $this->twf('status');

        self::assertSame(0, $status);
        self::assertStringContainsString('default (in use)', $output);
        self::assertStringContainsString('Organisation:  ACME', $output);
        self::assertStringContainsString('valid until', $output);
    }

    public function testStatusOfOneProfile(): void
    {
        $this->store()->saveProfile('default', self::profile());
        $this->store()->saveProfile('acme', self::profile(organisationCode: 'ACME'));

        [$status, $output] = $this->twf('status', '--profile=acme');

        self::assertSame(0, $status);
        self::assertStringNotContainsString('EXAMPLE', $output);
    }

    public function testTokenPrintsTheAccessToken(): void
    {
        $this->store()->saveProfile('default', self::profile());

        [$status, $output] = $this->twf('token');

        self::assertSame(0, $status);
        self::assertSame('access-token' . PHP_EOL, $output);
    }

    public function testLogout(): void
    {
        $this->store()->saveProfile('acme', self::profile());

        [$status, $output] = $this->twf('logout', '--profile=acme');

        self::assertSame(0, $status);
        self::assertStringContainsString('profile "acme"', $output);
        self::assertNull($this->store()->profile('acme'));

        [$status, , $error] = $this->twf('logout', '--profile=acme');

        self::assertSame(1, $status);
        self::assertStringContainsString('login --profile=acme', $error);
    }

    public function testLoginRefusesASecretWithoutAClientId(): void
    {
        [$status, , $error] = $this->twf('login', '--client-secret=secret');

        self::assertSame(1, $status);
        self::assertStringContainsString('--client-id', $error);
    }

    public function testManualLoginNamesTheRedirectUriAndChecksTheState(): void
    {
        [$status, $output, $error] = $this->twfWithInput(
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
        [$status, , $error] = $this->twfWithInput(
            'http://localhost:8765/callback?error=access_denied&error_description=No' . PHP_EOL,
            'login',
            '--manual',
            '--client-id=client-id',
            '--client-secret=client-secret',
        );

        self::assertSame(1, $status);
        self::assertStringContainsString('Twinfield returned "access_denied": No', $error);
    }

    public function testMessagesNameTheCommandAsItWasCalled(): void
    {
        [, , $error] = $this->twfAs('vendor/bin/twf', 'logout', '--profile=acme');

        self::assertStringContainsString('Run: vendor/bin/twf login --profile=acme', $error);

        [, , $error] = $this->twfAs('bin/twinfield', 'status');

        self::assertStringContainsString('Run: bin/twinfield login', $error);
    }

    public function testHelpNamesTheCommandAsItWasCalled(): void
    {
        [, $output] = $this->twfAs('vendor/bin/twf');

        self::assertStringContainsString('`twinfield` is the long name of `twf`', $output);

        [, $output] = $this->twfAs('vendor/bin/twinfield', 'list');

        self::assertStringContainsString('`twf` is the short name of `twinfield`', $output);
    }

    public function testACommandOnThePathIsNamedWithoutItsDirectory(): void
    {
        [, , $error] = $this->twfAs('/home/jdoe/.composer/vendor/bin/twf', 'status');

        self::assertStringContainsString('Run: twf login', $error);

        [, , $error] = $this->twfAs('C:\\Users\\jdoe\\vendor\\bin\\twf', 'status');

        self::assertStringContainsString('Run: twf login', $error);
    }

    /**
     * @return array{int, string, string}
     */
    private function twf(string ...$arguments): array
    {
        return $this->twfWithInput('', ...$arguments);
    }

    /**
     * @return array{int, string, string} Exit status, standard output and standard error.
     */
    private function twfWithInput(string $input, string ...$arguments): array
    {
        return $this->twfAs('vendor/bin/twf', ...$arguments, input: $input);
    }

    /**
     * @return array{int, string, string} Exit status, standard output and standard error.
     */
    private function twfAs(string $script, string ...$arguments): array
    {
        $input = $arguments['input'] ?? '';

        unset($arguments['input']);

        // bin/twf does this with the script name it was called with.
        CredentialsException::calledAs($script);

        $application = new Application($this->store());
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->setInputs([$input]);

        $status = $tester->run(self::input(...array_values($arguments)), ['capture_stderr_separately' => true]);

        return [$status, $tester->getDisplay(), $tester->getErrorOutput()];
    }

    /**
     * The command line as the array an ApplicationTester takes: `--profile=acme` becomes `['--profile' => 'acme']`.
     *
     * @return array<string, string|true>
     */
    private static function input(string ...$arguments): array
    {
        $input = [];

        foreach ($arguments as $argument) {
            if (!str_starts_with($argument, '--')) {
                $input['command'] = $argument;

                continue;
            }

            $parts = explode('=', $argument, 2);

            $input[$parts[0]] = $parts[1] ?? true;
        }

        return $input;
    }
}
