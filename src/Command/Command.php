<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Cli\Command;

use SolveBeam\Twinfield\Cli\CredentialStore;
use SolveBeam\Twinfield\Cli\Env;
use Symfony\Component\Console\Command\Command as ConsoleCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * A command of `twf`: works on the stored logins and takes `--profile`.
 */
abstract class Command extends ConsoleCommand
{
    public function __construct(protected readonly CredentialStore $store)
    {
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

    protected function profileName(InputInterface $input): string
    {
        return $this->option($input, 'profile') ?? Env::get('TWINFIELD_PROFILE') ?? 'default';
    }
}
