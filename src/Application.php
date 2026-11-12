<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Cli;

use Composer\InstalledVersions;
use RuntimeException;
use SolveBeam\Twinfield\Cli\Command\LoginCommand;
use SolveBeam\Twinfield\Cli\Command\LogoutCommand;
use SolveBeam\Twinfield\Cli\Command\StatusCommand;
use SolveBeam\Twinfield\Cli\Command\TokenCommand;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * The `twf` command, or `twinfield` in full.
 */
final class Application extends ConsoleApplication
{
    public function __construct(CredentialStore $store)
    {
        parent::__construct('Twinfield CLI', self::installedVersion());

        $this->addCommands([
            new LoginCommand($store),
            new StatusCommand($store),
            new TokenCommand($store),
            new LogoutCommand($store),
        ]);
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
