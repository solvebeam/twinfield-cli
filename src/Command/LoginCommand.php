<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Cli\Command;

use SolveBeam\Twinfield\Cli\CredentialsException;
use SolveBeam\Twinfield\Cli\Env;
use SolveBeam\Twinfield\Cli\LoopbackLogin;
use SolveBeam\Twinfield\Cli\Profile;
use SolveBeam\Twinfield\Connect\Provider\TwinfieldConnect;
use SolveBeam\Twinfield\Connect\Registration;
use SolveBeam\Twinfield\OAuth2\Scope;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'login', description: 'Log in with your browser')]
final class LoginCommand extends Command
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption('manual', null, InputOption::VALUE_NONE, 'Paste the redirected URL yourself, e.g. over SSH')
            ->addOption('rest', null, InputOption::VALUE_NONE, 'Also ask for all REST API scopes')
            ->addOption('scopes', null, InputOption::VALUE_REQUIRED, 'Ask for exactly these scopes: --scopes="A B"')
            ->addOption(
                'client-id',
                null,
                InputOption::VALUE_REQUIRED,
                'Your own Twinfield client instead of Twinfield Connect',
            )
            ->addOption('client-secret', null, InputOption::VALUE_REQUIRED, 'The secret of your own client')
            ->addOption(
                'redirect-uri',
                null,
                InputOption::VALUE_REQUIRED,
                'Default: TWINFIELD_REDIRECT_URI or ' . LoopbackLogin::REDIRECT_URI,
            )
            ->addOption('connect-url', null, InputOption::VALUE_REQUIRED, 'A Twinfield Connect you run yourself');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $profile = $this->profileName($input);
        $redirectUri = $this->option($input, 'redirect-uri') ?? Env::get('TWINFIELD_REDIRECT_URI')
            ?? LoopbackLogin::REDIRECT_URI;
        $clientId = $this->option($input, 'client-id') ?? Env::get('TWINFIELD_CLIENT_ID');
        $connectUrl = null;

        if ($clientId !== null) {
            $clientSecret = $this->option($input, 'client-secret') ?? Env::get('TWINFIELD_CLIENT_SECRET')
                ?? throw new CredentialsException('Give the secret of your client with --client-secret.');
        } elseif ($this->option($input, 'client-secret') !== null) {
            throw new CredentialsException('Give the ID of your client with --client-id.');
        } else {
            $connectUrl = $this->option($input, 'connect-url') ?? Env::get('TWINFIELD_CONNECT_URL')
                ?? TwinfieldConnect::CONNECT_URL;

            $registered = $this->store->connectClient($connectUrl);

            if ($registered === null || $registered['redirectUri'] !== $redirectUri) {
                $output->writeln(
                    sprintf('Registering the Twinfield CLI with Twinfield Connect (%s)…', $connectUrl),
                    OutputInterface::OUTPUT_RAW,
                );

                $client = (new Registration(connectUrl: $connectUrl))->register('Twinfield CLI', [$redirectUri]);

                $this->store->saveConnectClient($connectUrl, $client, $redirectUri);
            } else {
                $client = $registered['client'];
            }

            $clientId = $client->clientId;
            $clientSecret = $client->clientSecret;
        }

        $stream = ($input instanceof StreamableInputInterface ? $input->getStream() : null) ?? STDIN;

        [$token, $validation] = (new LoopbackLogin($stream, $output))->run(
            Profile::provider($clientId, $clientSecret, $redirectUri, $connectUrl),
            $redirectUri,
            $this->scopes($input),
            $input->getOption('manual') === true,
        );

        $this->store->saveProfile(
            $profile,
            Profile::fromLogin($clientId, $clientSecret, $redirectUri, $connectUrl, $token, $validation),
        );

        $output->writeln([
            '',
            'Logged in to Twinfield.',
            '  Organisation:  ' . $validation->organisationCode,
            '  User:          ' . $validation->organisationUserCode,
            '  Profile:       ' . $profile,
            '  Saved in:      ' . $this->store->path,
            '',
            'Use it in PHP:',
            '  $twinfield = Twinfield::create(Credentials::default());',
        ], OutputInterface::OUTPUT_RAW);

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function scopes(InputInterface $input): array
    {
        $scopes = $this->option($input, 'scopes');

        if ($scopes !== null) {
            return array_values(array_filter(explode(' ', $scopes)));
        }

        return Scope::values(...Scope::identity(), ...($input->getOption('rest') === true ? Scope::rest() : []));
    }
}
