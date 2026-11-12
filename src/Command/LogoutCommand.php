<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Cli\Command;

use SolveBeam\Twinfield\Cli\CredentialsException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'logout', description: 'Remove a login')]
final class LogoutCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $profile = $this->profileName($input);

        if (!$this->store->removeProfile($profile)) {
            throw CredentialsException::notLoggedIn($profile);
        }

        $output->writeln(
            sprintf('Logged out of Twinfield (profile "%s").', $profile),
            OutputInterface::OUTPUT_RAW,
        );

        return self::SUCCESS;
    }
}
