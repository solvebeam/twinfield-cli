<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Cli;

use SolveBeam\Twinfield\Connect\Provider\TwinfieldConnect;
use SolveBeam\Twinfield\Connect\Registration;
use SolveBeam\Twinfield\OAuth2\Scope;
use Throwable;

/**
 * The `twinfield` command.
 */
final readonly class Application
{
    private const string HELP = <<<'TXT'
        Log in to Twinfield for tests, examples and scripts.

        Usage:
          twinfield login     Log in with your browser
          twinfield status    Show the logins
          twinfield token     Print a valid access token
          twinfield logout    Remove a login

        `twf` is the short name of `twinfield`: twf login, twf status, …

        Options:
          --profile=NAME      The login to use, for more organisations (default: TWINFIELD_PROFILE or "default")
          --manual            login: paste the redirected URL yourself, e.g. over SSH
          --rest              login: also ask for all REST API scopes
          --scopes="A B"      login: ask for exactly these scopes
          --client-id=ID      login: your own Twinfield client instead of Twinfield Connect
          --client-secret=S   login: the secret of your own client
          --redirect-uri=URI  login: default TWINFIELD_REDIRECT_URI or http://localhost:8765/callback
          --connect-url=URL   login: a Twinfield Connect you run yourself

        In PHP:
          $twinfield = Twinfield::create(Credentials::default());
        TXT;

    private const array OPTIONS = [
        'profile', 'manual', 'rest', 'scopes', 'client-id', 'client-secret', 'redirect-uri', 'connect-url', 'help',
    ];

    /**
     * @param resource $input
     * @param resource $output
     * @param resource $error
     */
    public function __construct(
        private mixed $input,
        private mixed $output,
        private mixed $error,
        private CredentialStore $store,
    ) {
    }

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        try {
            [$command, $options] = $this->parse(array_slice($argv, 1));

            if (isset($options['help'])) {
                $command = 'help';
            }

            return match ($command) {
                'login' => $this->login($options),
                'status' => $this->status($options),
                'token' => $this->token($options),
                'logout' => $this->logout($options),
                null, 'help' => $this->help(),
                default => throw new CredentialsException(
                    sprintf('Unknown command "%s". See: twinfield help', $command),
                ),
            };
        } catch (Throwable $exception) {
            fwrite($this->error, 'Error: ' . $exception->getMessage() . PHP_EOL);

            return 1;
        }
    }

    /**
     * @param array<string, string|true> $options
     */
    private function login(array $options): int
    {
        $profile = $this->profileName($options);
        $redirectUri = $this->option($options, 'redirect-uri') ?? Env::get('TWINFIELD_REDIRECT_URI')
            ?? LoopbackLogin::REDIRECT_URI;
        $clientId = $this->option($options, 'client-id') ?? Env::get('TWINFIELD_CLIENT_ID');
        $connectUrl = null;

        if ($clientId !== null) {
            $clientSecret = $this->option($options, 'client-secret') ?? Env::get('TWINFIELD_CLIENT_SECRET')
                ?? throw new CredentialsException('Give the secret of your client with --client-secret.');
        } elseif ($this->option($options, 'client-secret') !== null) {
            throw new CredentialsException('Give the ID of your client with --client-id.');
        } else {
            $connectUrl = $this->option($options, 'connect-url') ?? Env::get('TWINFIELD_CONNECT_URL')
                ?? TwinfieldConnect::CONNECT_URL;

            $registered = $this->store->connectClient($connectUrl);

            if ($registered === null || $registered['redirectUri'] !== $redirectUri) {
                $this->line(sprintf('Registering the Twinfield CLI with Twinfield Connect (%s)…', $connectUrl));

                $client = (new Registration(connectUrl: $connectUrl))->register('Twinfield CLI', [$redirectUri]);

                $this->store->saveConnectClient($connectUrl, $client, $redirectUri);
            } else {
                $client = $registered['client'];
            }

            $clientId = $client->clientId;
            $clientSecret = $client->clientSecret;
        }

        [$token, $validation] = (new LoopbackLogin($this->input, $this->output))->run(
            Profile::provider($clientId, $clientSecret, $redirectUri, $connectUrl),
            $redirectUri,
            $this->scopes($options),
            isset($options['manual']),
        );

        $this->store->saveProfile(
            $profile,
            Profile::fromLogin($clientId, $clientSecret, $redirectUri, $connectUrl, $token, $validation),
        );

        $this->line();
        $this->line('Logged in to Twinfield.');
        $this->line('  Organisation:  ' . $validation->organisationCode);
        $this->line('  User:          ' . $validation->organisationUserCode);
        $this->line('  Profile:       ' . $profile);
        $this->line('  Saved in:      ' . $this->store->path);
        $this->line();
        $this->line('Use it in PHP:');
        $this->line('  $twinfield = Twinfield::create(Credentials::default());');

        return 0;
    }

    /**
     * @param array<string, string|true> $options
     */
    private function status(array $options): int
    {
        if (Env::get('TWINFIELD_REFRESH_TOKEN') !== null) {
            $this->line('TWINFIELD_REFRESH_TOKEN is set: Credentials::default() uses it instead of a profile.');
            $this->line();
        }

        $profiles = $this->store->profiles();

        $only = $this->option($options, 'profile');

        if ($only !== null) {
            $profiles = array_intersect_key($profiles, [$only => true]);
        }

        if ($profiles === []) {
            throw CredentialsException::notLoggedIn($only ?? 'default');
        }

        $current = Env::get('TWINFIELD_PROFILE') ?? 'default';

        foreach ($profiles as $name => $profile) {
            $this->line($name . ($name === $current ? ' (in use)' : ''));
            $this->line('  Organisation:  ' . $profile->organisationCode);
            $this->line('  User:          ' . $profile->userCode);
            $this->line('  Cluster:       ' . $profile->clusterUrl);
            $this->line('  Client:        ' . ($profile->usesConnect()
                ? 'Twinfield Connect (' . $profile->connectUrl . ')'
                : $profile->clientId));
            $this->line('  Access token:  ' . ($profile->hasValidAccessToken()
                ? 'valid until ' . date('Y-m-d H:i:s T', (int) $profile->expires)
                : 'expired, refreshed on next use'));
            $this->line();
        }

        $this->line('Saved in ' . $this->store->path);

        return 0;
    }

    /**
     * @param array<string, string|true> $options
     */
    private function token(array $options): int
    {
        $this->line((new ProfileAccessTokenProvider($this->store, $this->profileName($options)))->accessToken());

        return 0;
    }

    /**
     * @param array<string, string|true> $options
     */
    private function logout(array $options): int
    {
        $profile = $this->profileName($options);

        if (!$this->store->removeProfile($profile)) {
            throw CredentialsException::notLoggedIn($profile);
        }

        $this->line(sprintf('Logged out of Twinfield (profile "%s").', $profile));

        return 0;
    }

    private function help(): int
    {
        $this->line(self::HELP);

        return 0;
    }

    /**
     * @param array<string, string|true> $options
     * @return list<string>
     */
    private function scopes(array $options): array
    {
        $scopes = $this->option($options, 'scopes');

        if ($scopes !== null) {
            return array_values(array_filter(explode(' ', $scopes)));
        }

        return Scope::values(...Scope::identity(), ...(isset($options['rest']) ? Scope::rest() : []));
    }

    /**
     * @param array<string, string|true> $options
     */
    private function profileName(array $options): string
    {
        return $this->option($options, 'profile') ?? Env::get('TWINFIELD_PROFILE') ?? 'default';
    }

    /**
     * @param array<string, string|true> $options
     */
    private function option(array $options, string $name): ?string
    {
        $value = $options[$name] ?? null;

        if ($value === true) {
            throw new CredentialsException(sprintf('Option --%s needs a value: --%s=…', $name, $name));
        }

        return $value === '' ? null : $value;
    }

    /**
     * @param list<string> $arguments
     * @return array{?string, array<string, string|true>}
     */
    private function parse(array $arguments): array
    {
        $command = null;
        $options = [];

        foreach ($arguments as $argument) {
            if (!str_starts_with($argument, '--')) {
                if ($command !== null) {
                    throw new CredentialsException(sprintf('Unexpected argument "%s".', $argument));
                }

                $command = $argument;

                continue;
            }

            $parts = explode('=', substr($argument, 2), 2);

            if (!in_array($parts[0], self::OPTIONS, true)) {
                throw new CredentialsException(sprintf('Unknown option "--%s". See: twinfield help', $parts[0]));
            }

            $options[$parts[0]] = $parts[1] ?? true;
        }

        return [$command, $options];
    }

    private function line(string $text = ''): void
    {
        fwrite($this->output, $text . PHP_EOL);
    }
}
