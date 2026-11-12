<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Cli\Command;

use SolveBeam\Twinfield\Cli\ProfileAccessTokenProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'token', description: 'Print a valid access token')]
final class TokenCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Also with --quiet: the token is the result of the command, e.g. in $(twf token).
        $output->writeln(
            (new ProfileAccessTokenProvider($this->store, $this->profileName($input)))->accessToken(),
            OutputInterface::OUTPUT_RAW | OutputInterface::VERBOSITY_QUIET,
        );

        return self::SUCCESS;
    }
}
