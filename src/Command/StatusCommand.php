<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Cli\Command;

use SolveBeam\Twinfield\Cli\CredentialsException;
use SolveBeam\Twinfield\Cli\Env;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'status', description: 'Show the logins')]
final class StatusCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $lines = [];

        if (Env::get('TWINFIELD_REFRESH_TOKEN') !== null) {
            $lines[] = 'TWINFIELD_REFRESH_TOKEN is set: Credentials::default() uses it instead of a profile.';
            $lines[] = '';
        }

        $profiles = $this->store->profiles();

        $only = $this->option($input, 'profile');

        if ($only !== null) {
            $profiles = array_intersect_key($profiles, [$only => true]);
        }

        if ($profiles === []) {
            $output->writeln($lines, OutputInterface::OUTPUT_RAW);

            throw CredentialsException::notLoggedIn($only ?? 'default');
        }

        $current = Env::get('TWINFIELD_PROFILE') ?? 'default';

        foreach ($profiles as $name => $profile) {
            $lines[] = $name . ($name === $current ? ' (in use)' : '');
            $lines[] = '  Organisation:  ' . $profile->organisationCode;
            $lines[] = '  User:          ' . $profile->userCode;
            $lines[] = '  Cluster:       ' . $profile->clusterUrl;
            $lines[] = '  Client:        ' . ($profile->usesConnect()
                ? 'Twinfield Connect (' . $profile->connectUrl . ')'
                : $profile->clientId);
            $lines[] = '  Access token:  ' . ($profile->hasValidAccessToken()
                ? 'valid until ' . date('Y-m-d H:i:s T', (int) $profile->expires)
                : 'expired, refreshed on next use');
            $lines[] = '';
        }

        $lines[] = 'Saved in ' . $this->store->path;

        $output->writeln($lines, OutputInterface::OUTPUT_RAW);

        return self::SUCCESS;
    }
}
