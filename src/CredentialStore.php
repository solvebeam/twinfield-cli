<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Cli;

use Closure;
use JsonException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use SolveBeam\Twinfield\Connect\ConnectClient;

/**
 * The credentials file, `~/.config/twinfield/credentials.json` by default:
 *
 *   {
 *       "connect_clients": {"https://connect.twinfield.solvebeam.com": {"client_id": …, …}},
 *       "profiles": {"default": {"client_id": …, "refresh_token": …, "access_token": …, …}}
 *   }
 *
 * Only you can read the file (0600). Writes take an exclusive lock, so two processes that refresh
 * the access token at the same time do not overwrite each other's refresh token.
 */
final readonly class CredentialStore
{
    public function __construct(
        public string $path,
    ) {
    }

    /**
     * `$TWINFIELD_CONFIG_DIR/credentials.json`, else `$XDG_CONFIG_HOME/twinfield/credentials.json`,
     * else `~/.config/twinfield/credentials.json`.
     */
    public static function default(): self
    {
        $directory = Env::get('TWINFIELD_CONFIG_DIR');

        if ($directory === null) {
            $configHome = Env::get('XDG_CONFIG_HOME');

            if ($configHome === null) {
                $home = Env::get('HOME') ?? Env::get('USERPROFILE') ?? throw new CredentialsException(
                    'Cannot find your home directory. Set TWINFIELD_CONFIG_DIR.',
                );

                $configHome = rtrim($home, '/\\') . '/.config';
            }

            $directory = rtrim($configHome, '/\\') . '/twinfield';
        }

        return new self(rtrim($directory, '/\\') . '/credentials.json');
    }

    public function profile(string $name): ?Profile
    {
        $profile = $this->read()['profiles'][$name] ?? null;

        return is_array($profile) ? Profile::fromArray($profile) : null;
    }

    /**
     * @return array<string, Profile>
     */
    public function profiles(): array
    {
        $profiles = [];

        foreach ($this->read()['profiles'] ?? [] as $name => $profile) {
            if (is_array($profile)) {
                $profiles[(string) $name] = Profile::fromArray($profile);
            }
        }

        return $profiles;
    }

    public function saveProfile(string $name, Profile $profile): void
    {
        $this->update(static function (array $data) use ($name, $profile): array {
            $data['profiles'][$name] = $profile->toArray();

            return $data;
        });
    }

    public function removeProfile(string $name): bool
    {
        // Nothing to remove; do not create the file.
        if (!is_file($this->path)) {
            return false;
        }

        $removed = false;

        $this->update(static function (array $data) use ($name, &$removed): array {
            $removed = isset($data['profiles'][$name]);

            unset($data['profiles'][$name]);

            return $data;
        });

        return $removed;
    }

    /**
     * The client this machine registered with a Twinfield Connect, and its redirect URI.
     *
     * @return array{client: ConnectClient, redirectUri: string}|null
     */
    public function connectClient(string $connectUrl): ?array
    {
        $client = $this->read()['connect_clients'][$connectUrl] ?? null;

        if (
            !is_array($client)
            || !is_string($client['client_id'] ?? null)
            || !is_string($client['client_secret'] ?? null)
            || !is_string($client['redirect_uri'] ?? null)
        ) {
            return null;
        }

        return [
            'client' => new ConnectClient($client['client_id'], $client['client_secret']),
            'redirectUri' => $client['redirect_uri'],
        ];
    }

    public function saveConnectClient(string $connectUrl, ConnectClient $client, string $redirectUri): void
    {
        $this->update(static function (array $data) use ($connectUrl, $client, $redirectUri): array {
            $data['connect_clients'][$connectUrl] = [
                'client_id' => $client->clientId,
                'client_secret' => $client->clientSecret,
                'redirect_uri' => $redirectUri,
            ];

            return $data;
        });
    }

    /**
     * Gets a new access token for a profile and stores it, with the new refresh token.
     *
     * When another process refreshed the profile in the meantime, its access token is used.
     */
    public function refresh(string $name): Profile
    {
        $profile = null;

        $this->update(static function (array $data) use ($name, &$profile): array {
            $stored = $data['profiles'][$name] ?? null;

            if (!is_array($stored)) {
                throw CredentialsException::notLoggedIn($name);
            }

            $profile = Profile::fromArray($stored);

            if ($profile->hasValidAccessToken()) {
                return $data;
            }

            $provider = $profile->oauthProvider();

            try {
                $token = $provider->getAccessToken('refresh_token', ['refresh_token' => $profile->refreshToken]);
                $validation = $provider->getAccessTokenValidation($token);
            } catch (IdentityProviderException $exception) {
                throw new CredentialsException(sprintf(
                    'The Twinfield login has expired or was revoked (%s). Log in again with: %s',
                    $exception->getMessage(),
                    CredentialsException::loginCommand($name),
                ), 0, $exception);
            }

            $profile = $profile->withTokens($token, $validation);

            $data['profiles'][$name] = $profile->toArray();

            return $data;
        });

        assert($profile instanceof Profile);

        return $profile;
    }

    /**
     * @return array<mixed>
     */
    private function read(): array
    {
        if (!is_file($this->path)) {
            return [];
        }

        $handle = fopen($this->path, 'r') ?: throw new CredentialsException('Cannot read ' . $this->path);

        try {
            flock($handle, LOCK_SH);

            return $this->decode((string) stream_get_contents($handle));
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * Reads, changes and writes the file under an exclusive lock. When the change throws, the
     * file stays as it was.
     *
     * @param Closure(array<mixed>): array<mixed> $change
     */
    private function update(Closure $change): void
    {
        $directory = dirname($this->path);

        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new CredentialsException('Cannot create ' . $directory);
        }

        $handle = fopen($this->path, 'c+') ?: throw new CredentialsException('Cannot write ' . $this->path);

        chmod($this->path, 0600);

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new CredentialsException('Cannot lock ' . $this->path);
            }

            $data = $change($this->decode((string) stream_get_contents($handle)));

            // Before the file is emptied: when this throws, the file stays as it was.
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, $json . "\n");
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * @return array<mixed>
     */
    private function decode(string $json): array
    {
        if (trim($json) === '') {
            return [];
        }

        try {
            $data = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new CredentialsException(sprintf('%s is not valid JSON.', $this->path), 0, $exception);
        }

        return is_array($data) ? $data : throw new CredentialsException($this->path . ' is not a JSON object.');
    }
}
