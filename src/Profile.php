<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Cli;

use League\OAuth2\Client\Token\AccessTokenInterface;
use SolveBeam\Twinfield\Connect\ConnectClient;
use SolveBeam\Twinfield\Connect\Provider\TwinfieldConnect;
use SolveBeam\Twinfield\OAuth2\AccessTokenValidation;
use SolveBeam\Twinfield\OAuth2\Provider\AbstractTwinfieldProvider;
use SolveBeam\Twinfield\OAuth2\Provider\Twinfield;

/**
 * One Twinfield login: the OAuth client it used, the refresh token, the last access token and who
 * it belongs to.
 *
 * A profile without a Connect URL uses your own Twinfield client; with one, it logs in through
 * Twinfield Connect.
 */
final readonly class Profile
{
    /** Refresh this many seconds before the access token expires. */
    private const int EXPIRY_MARGIN = 60;

    public function __construct(
        public string $clientId,
        public string $clientSecret,
        public string $redirectUri,
        public ?string $connectUrl,
        public string $refreshToken,
        public string $organisationId,
        public string $organisationCode,
        public string $userCode,
        public string $clusterUrl,
        public ?string $accessToken = null,
        public ?int $expires = null,
    ) {
    }

    /**
     * The profile of a new login.
     */
    public static function fromLogin(
        string $clientId,
        string $clientSecret,
        string $redirectUri,
        ?string $connectUrl,
        AccessTokenInterface $token,
        AccessTokenValidation $validation,
    ): self {
        $refreshToken = $token->getRefreshToken() ?? throw new CredentialsException(
            'Twinfield did not return a refresh token. Request the "offline_access" scope.',
        );

        return (new self(
            $clientId,
            $clientSecret,
            $redirectUri,
            $connectUrl,
            $refreshToken,
            $validation->organisationId,
            $validation->organisationCode,
            $validation->organisationUserCode,
            $validation->clusterUrl,
        ))->withTokens($token, $validation);
    }

    public static function provider(
        string $clientId,
        string $clientSecret,
        string $redirectUri,
        ?string $connectUrl,
    ): AbstractTwinfieldProvider {
        if ($connectUrl === null) {
            return new Twinfield([
                'clientId' => $clientId,
                'clientSecret' => $clientSecret,
                'redirectUri' => $redirectUri,
            ]);
        }

        return TwinfieldConnect::forClient(
            new ConnectClient($clientId, $clientSecret),
            $redirectUri,
            ['connectUrl' => $connectUrl],
        );
    }

    public function oauthProvider(): AbstractTwinfieldProvider
    {
        return self::provider($this->clientId, $this->clientSecret, $this->redirectUri, $this->connectUrl);
    }

    public function usesConnect(): bool
    {
        return $this->connectUrl !== null;
    }

    public function hasValidAccessToken(): bool
    {
        return $this->accessToken !== null
            && $this->expires !== null
            && $this->expires - self::EXPIRY_MARGIN > time();
    }

    /**
     * This profile with a new access token and, when Twinfield sent one, a new refresh token.
     */
    public function withTokens(AccessTokenInterface $token, AccessTokenValidation $validation): self
    {
        return new self(
            $this->clientId,
            $this->clientSecret,
            $this->redirectUri,
            $this->connectUrl,
            $token->getRefreshToken() ?? $this->refreshToken,
            $validation->organisationId,
            $validation->organisationCode,
            $validation->organisationUserCode,
            rtrim($validation->clusterUrl, '/'),
            $token->getToken(),
            $token->getExpires() ?? $validation->exp,
        );
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $string = static fn (string $key): string => is_string($data[$key] ?? null) && $data[$key] !== ''
            ? $data[$key]
            : throw new CredentialsException(sprintf('The credentials file has a profile without "%s".', $key));

        return new self(
            $string('client_id'),
            $string('client_secret'),
            $string('redirect_uri'),
            is_string($data['connect_url'] ?? null) ? $data['connect_url'] : null,
            $string('refresh_token'),
            $string('organisation_id'),
            $string('organisation_code'),
            $string('user_code'),
            $string('cluster_url'),
            is_string($data['access_token'] ?? null) ? $data['access_token'] : null,
            is_int($data['expires'] ?? null) ? $data['expires'] : null,
        );
    }

    /**
     * @return array<string, string|int|null>
     */
    public function toArray(): array
    {
        return [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'connect_url' => $this->connectUrl,
            'refresh_token' => $this->refreshToken,
            'organisation_id' => $this->organisationId,
            'organisation_code' => $this->organisationCode,
            'user_code' => $this->userCode,
            'cluster_url' => $this->clusterUrl,
            'access_token' => $this->accessToken,
            'expires' => $this->expires,
        ];
    }
}
