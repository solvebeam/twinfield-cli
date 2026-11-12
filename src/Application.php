<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Cli;

use Composer\InstalledVersions;
use Psr\Http\Client\ClientInterface;
use RuntimeException;
use SolveBeam\Twinfield\Cli\Command\LoginCommand;
use SolveBeam\Twinfield\Cli\Command\LogoutCommand;
use SolveBeam\Twinfield\Cli\Command\OfficeListCommand;
use SolveBeam\Twinfield\Cli\Command\ProcessXmlCommand;
use SolveBeam\Twinfield\Cli\Command\StatusCommand;
use SolveBeam\Twinfield\Cli\Command\TokenCommand;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * The `twf` command, or `twinfield` in full.
 */
final class Application extends ConsoleApplication
{
    /**
     * @param ClientInterface|null $httpClient For the requests to Twinfield; null discovers an installed client.
     */
    public function __construct(CredentialStore $store, ?ClientInterface $httpClient = null)
    {
        parent::__construct('Twinfield CLI', self::installedVersion());

        $this->addCommands([
            new LoginCommand($store),
            new StatusCommand($store),
            new TokenCommand($store),
            new LogoutCommand($store),
            new OfficeListCommand($store, $httpClient),
            new ProcessXmlCommand($store, $httpClient),
        ]);
    }

    /**
     * Reads `twf office list` as the command `office:list`, see `arguments()`.
     */
    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        if ($input === null) {
            $argv = array_values(array_filter((array) ($_SERVER['argv'] ?? []), is_string(...)));

            $input = new ArgvInput([$argv[0] ?? '', ...$this->arguments(array_slice($argv, 1))]);
        }

        return parent::run($input, $output);
    }

    /**
     * The arguments as Symfony reads them: a command in a group is written with a space, like
     * `gh repo list`, and is the command `office:list` for Symfony.
     *
     * - `office list --json` becomes `office:list --json`.
     * - `office` alone becomes `list office`, which shows the commands of the group.
     * - `help office list` becomes `help office:list`.
     *
     * @internal
     *
     * @param list<string> $arguments The arguments after the script name.
     *
     * @return list<string>
     */
    public function arguments(array $arguments): array
    {
        $group = $arguments[0] ?? '';

        // `help office list` is the help of `office:list`, `help office` shows the group.
        if ($group === 'help' && !$this->has($arguments[1] ?? '')) {
            $rest = $this->arguments(array_slice($arguments, 1));

            return ($rest[0] ?? '') === 'list' ? $rest : ['help', ...$rest];
        }

        if ($group === '' || str_starts_with($group, '-') || $this->has($group)) {
            return $arguments;
        }

        $command = $arguments[1] ?? '';

        if ($command !== '' && !str_starts_with($command, '-') && $this->has($group . ':' . $command)) {
            return [$group . ':' . $command, ...array_slice($arguments, 2)];
        }

        if (($command === '' || str_starts_with($command, '-')) && in_array($group, $this->getNamespaces(), true)) {
            // Without --help: that would show the help of `list` itself.
            return ['list', $group, ...array_diff(array_slice($arguments, 1), ['--help', '-h'])];
        }

        return $arguments;
    }

    public function getHelp(): string
    {
        $name = CredentialsException::commandName();

        return implode(PHP_EOL, [
            parent::getHelp(),
            '',
            'Twinfield CLI brings Twinfield to your terminal.',
            '',
            $name === 'twinfield'
                ? '`twf` is the short name of `twinfield`: twf login, twf status, …'
                : '`twinfield` is the long name of `twf`: twinfield login, twinfield status, …',
            '',
            'A command in a group is written with a space:',
            '  ' . $name . ' office list',
            '',
            'In PHP:',
            '  $twinfield = Twinfield::create(Credentials::default());',
        ]);
    }

    /**
     * Prints an error as one line, so a command in the message stays in one piece; `-v` shows the trace.
     */
    public function renderThrowable(Throwable $e, OutputInterface $output): void
    {
        if ($output->isVerbose()) {
            parent::renderThrowable($e, $output);

            return;
        }

        $output->writeln('Error: ' . OutputFormatter::escape($e->getMessage()), OutputInterface::VERBOSITY_QUIET);
    }

    protected function doRunCommand(Command $command, InputInterface $input, OutputInterface $output): int
    {
        try {
            return parent::doRunCommand($command, $input, $output);
        } catch (ExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            // The exit status of an error is 1, not the code of the exception, e.g. an HTTP status.
            if (in_array($exception->getCode(), [0, 1], true)) {
                throw $exception;
            }

            throw new RuntimeException($exception->getMessage(), 1, $exception);
        }
    }

    private static function installedVersion(): string
    {
        return InstalledVersions::isInstalled('solvebeam/twinfield-cli')
            ? InstalledVersions::getPrettyVersion('solvebeam/twinfield-cli') ?? 'UNKNOWN'
            : 'UNKNOWN';
    }
}
