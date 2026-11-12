<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Cli;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessTokenInterface;
use SolveBeam\Twinfield\OAuth2\AccessTokenValidation;
use SolveBeam\Twinfield\OAuth2\Provider\AbstractTwinfieldProvider;

/**
 * Browser login: opens the Twinfield login, catches the redirect on localhost and exchanges the
 * code for tokens.
 *
 * The same provider object builds the login URL and exchanges the code, so it still has the PKCE
 * code Twinfield Connect needs.
 */
final readonly class LoopbackLogin
{
    public const string REDIRECT_URI = 'http://localhost:8765/callback';

    private const int TIMEOUT = 300;

    /**
     * @param resource $input
     * @param resource $output
     */
    public function __construct(
        private mixed $input,
        private mixed $output,
    ) {
    }

    /**
     * @param list<string> $scopes
     * @return array{AccessTokenInterface, AccessTokenValidation}
     */
    public function run(
        AbstractTwinfieldProvider $provider,
        string $redirectUri,
        array $scopes,
        bool $manual = false,
    ): array {
        $authorizationUrl = $provider->getAuthorizationUrl([
            'scope' => $scopes,
            // OpenID Connect replay protection; Twinfield lists it as an authorize parameter.
            'nonce' => bin2hex(random_bytes(16)),
        ]);
        $state = $provider->getState();

        if ($manual) {
            $this->line('Open the Twinfield login in your browser: ' . $authorizationUrl);
        } else {
            $this->line('Opening the Twinfield login in your browser…');
            $this->line('If it does not open, visit: ' . $authorizationUrl);

            self::openBrowser($authorizationUrl);
        }

        $query = $manual ? $this->askForRedirect($redirectUri) : $this->waitForRedirect($redirectUri);

        if (isset($query['error'])) {
            throw new CredentialsException(sprintf(
                'Twinfield returned "%s": %s',
                $query['error'],
                $query['error_description'] ?? 'no description',
            ));
        }

        if (!hash_equals($state, $query['state'] ?? '')) {
            throw new CredentialsException('The redirect does not belong to this login. Try again.');
        }

        if (!isset($query['code'])) {
            throw new CredentialsException('The redirect does not contain an authorization code.');
        }

        try {
            $token = $provider->getAccessToken('authorization_code', ['code' => $query['code']]);

            return [$token, $provider->getAccessTokenValidation($token)];
        } catch (IdentityProviderException $exception) {
            throw new CredentialsException('Token request failed: ' . $exception->getMessage(), 0, $exception);
        }
    }

    private function line(string $text = ''): void
    {
        fwrite($this->output, $text . PHP_EOL);
    }

    private static function openBrowser(string $url): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            // Without a shell: escapeshellarg() replaces the "%" of the URL with a space on Windows.
            $process = @proc_open(['rundll32', 'url.dll,FileProtocolHandler', $url], [], $pipes);

            if (is_resource($process)) {
                proc_close($process);
            }

            return;
        }

        $command = match (PHP_OS_FAMILY) {
            'Darwin' => 'open ' . escapeshellarg($url),
            'Linux' => 'xdg-open ' . escapeshellarg($url) . ' >/dev/null 2>&1 &',
            default => null,
        };

        if ($command !== null) {
            exec($command);
        }
    }

    /**
     * Waits for the OAuth redirect on the redirect URI and returns its query parameters.
     *
     * @return array<array-key, string>
     */
    private function waitForRedirect(string $redirectUri): array
    {
        $parts = parse_url($redirectUri);
        $port = $parts['port'] ?? 80;
        $path = $parts['path'] ?? '/';
        $errorMessage = null;

        // Browsers may resolve "localhost" to IPv4 or IPv6, so listen on both.
        $servers = [];

        foreach (['127.0.0.1', '[::1]'] as $host) {
            $server = @stream_socket_server(sprintf('tcp://%s:%d', $host, $port), $errorCode, $errorMessage);

            if ($server !== false) {
                $servers[] = $server;
            }
        }

        if ($servers === []) {
            throw new CredentialsException(sprintf(
                'Cannot listen on port %d (%s). Try: vendor/bin/twinfield login --manual',
                $port,
                $errorMessage ?? 'unknown',
            ));
        }

        $this->line(sprintf('Waiting for the login on %s (max %d seconds)…', $redirectUri, self::TIMEOUT));

        $deadline = time() + self::TIMEOUT;

        try {
            while (time() < $deadline) {
                $read = $servers;
                $write = $except = null;

                if (stream_select($read, $write, $except, 1) < 1) {
                    continue;
                }

                // stream_select() keeps the array keys, so the ready socket is not always at index 0.
                $connection = stream_socket_accept(reset($read), 1);

                if ($connection === false) {
                    continue;
                }

                // Browsers open connections they never use; do not wait for those.
                stream_set_timeout($connection, 2);

                $target = explode(' ', (string) fgets($connection))[1] ?? '/';

                if (parse_url($target, PHP_URL_PATH) !== $path) {
                    fwrite($connection, "HTTP/1.1 404 Not Found\r\nContent-Length: 0\r\nConnection: close\r\n\r\n");
                    fclose($connection);

                    continue;
                }

                parse_str((string) parse_url($target, PHP_URL_QUERY), $query);

                $html = isset($query['code'])
                    ? '<h1>Logged in to Twinfield</h1><p>You can close this tab and return to the terminal.</p>'
                    : '<h1>Twinfield login failed</h1><p>See the terminal for details.</p>';

                fwrite($connection, sprintf(
                    "HTTP/1.1 200 OK\r\nContent-Type: text/html; charset=utf-8\r\n"
                        . "Content-Length: %d\r\nConnection: close\r\n\r\n%s",
                    strlen($html),
                    $html,
                ));
                fclose($connection);

                return array_map(strval(...), array_filter($query, is_string(...)));
            }
        } finally {
            foreach ($servers as $server) {
                fclose($server);
            }
        }

        throw new CredentialsException('Timed out waiting for the login.');
    }

    /**
     * @return array<array-key, string>
     */
    private function askForRedirect(string $redirectUri): array
    {
        $this->line('After logging in, your browser goes to ' . $redirectUri . ' (the page may not load).');

        fwrite($this->output, 'Paste the full URL from the address bar: ');

        $url = trim((string) fgets($this->input));

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return array_map(strval(...), array_filter($query, is_string(...)));
    }
}
