<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Cli;

use SolveBeam\Twinfield\Authentication\AccessTokenProvider;
use SolveBeam\Twinfield\Organisation\Organisation;

/**
 * Access tokens of a profile in the credentials file. A valid access token in the file is used
 * as it is; an expired one is refreshed and the new tokens are written back.
 */
final class ProfileAccessTokenProvider implements AccessTokenProvider
{
    private ?Profile $current = null;

    public function __construct(
        private readonly CredentialStore $store,
        private readonly string $profile = 'default',
    ) {
    }

    public function accessToken(): string
    {
        return (string) $this->current()->accessToken;
    }

    public function clusterUrl(): string
    {
        return rtrim($this->current()->clusterUrl, '/');
    }

    public function userCode(): string
    {
        return $this->current()->userCode;
    }

    public function organisation(): Organisation
    {
        $profile = $this->current();

        return new Organisation($profile->organisationId, $profile->organisationCode);
    }

    private function current(): Profile
    {
        if ($this->current?->hasValidAccessToken()) {
            return $this->current;
        }

        $profile = $this->store->profile($this->profile) ?? throw CredentialsException::notLoggedIn($this->profile);

        if (!$profile->hasValidAccessToken()) {
            $profile = $this->store->refresh($this->profile);
        }

        return $this->current = $profile;
    }
}
