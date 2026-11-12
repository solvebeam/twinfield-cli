<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Cli\Command;

use Psr\Http\Client\ClientInterface;
use SolveBeam\Twinfield\Cli\CredentialStore;
use SolveBeam\Twinfield\Cli\Env;
use SolveBeam\Twinfield\Cli\ProfileAccessTokenProvider;
use SolveBeam\Twinfield\Twinfield;
use Symfony\Component\Console\Command\Command as ConsoleCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

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

    protected function profileName(InputInterface $input): string
    {
        return $this->option($input, 'profile') ?? Env::get('TWINFIELD_PROFILE') ?? 'default';
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
