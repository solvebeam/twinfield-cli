<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Cli\Command;

use InvalidArgumentException;
use Psr\Http\Client\ClientInterface;
use SolveBeam\Twinfield\Cli\CredentialStore;
use SolveBeam\Twinfield\Cli\Env;
use SolveBeam\Twinfield\Cli\ProfileAccessTokenProvider;
use SolveBeam\Twinfield\Twinfield;
use Symfony\Component\Console\Command\Command as ConsoleCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * A command of `twf`: works with the stored logins and takes `--profile`.
 */
abstract class Command extends ConsoleCommand
{
    /**
     * @param ClientInterface|null $httpClient For the requests to Twinfield; null discovers an installed client.
     */
    public function __construct(
        protected readonly CredentialStore $store,
        protected readonly ?ClientInterface $httpClient = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'profile',
            null,
            InputOption::VALUE_REQUIRED,
            'The login to use, for more organisations (default: TWINFIELD_PROFILE or "default")',
        );
    }

    /**
     * The value of an option, or null when it is not given or empty.
     */
    protected function option(InputInterface $input, string $name): ?string
    {
        $value = $input->getOption($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * The value of `--limit` of a command that lists: the most items to print, or null for all.
     */
    protected function limit(InputInterface $input): ?int
    {
        $limit = $input->getOption('limit');

        if ($limit === null) {
            return null;
        }

        if (!is_string($limit) || !ctype_digit($limit) || (int) $limit < 1) {
            throw new InvalidArgumentException('--limit must be a number of 1 or more.');
        }

        return (int) $limit;
    }

    /**
     * A row of a list as a tab-separated line, for a pipe: `twf office list | cut -f1`.
     *
     * @param list<string> $row
     */
    protected static function line(array $row): string
    {
        return implode("\t", array_map(
            // A tab or a line break in a value would start a new column or line.
            static fn (string $value): string => trim((string) preg_replace('/[\t\r\n]+/', ' ', $value)),
            $row,
        ));
    }

    protected function profileName(InputInterface $input): string
    {
        return $this->option($input, 'profile') ?? Env::get('TWINFIELD_PROFILE') ?? 'default';
    }

    /**
     * True when the output goes to a terminal, false in a pipe or a file: output for people or for a script.
     */
    protected function isTerminal(OutputInterface $output): bool
    {
        return $output instanceof StreamOutput && stream_isatty($output->getStream());
    }

    /**
     * The Twinfield client of the login in use.
     */
    protected function twinfield(InputInterface $input): Twinfield
    {
        return Twinfield::create(
            new ProfileAccessTokenProvider($this->store, $this->profileName($input)),
            $this->httpClient,
        );
    }
}
