<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Cli;

use SolveBeam\Twinfield\Authentication\AccessTokenProvider;
use SolveBeam\Twinfield\Authentication\RefreshTokenAccessTokenProvider;
use SolveBeam\Twinfield\Connect\Provider\TwinfieldConnect;
use SolveBeam\Twinfield\OAuth2\Provider\AbstractTwinfieldProvider;

/**
 * The Twinfield login for tests, examples and scripts, like Application Default Credentials of
 * Google Cloud:
 *
 *   $twinfield = Twinfield::create(Credentials::default());
 *
 * It uses, in this order:
 *
 * 1. Environment variables, for CI and only when you name no profile: `TWINFIELD_REFRESH_TOKEN`
 *    with `TWINFIELD_CLIENT_ID` and `TWINFIELD_CLIENT_SECRET` (your own Twinfield client), or with
 *    `TWINFIELD_CONNECT_CLIENT_ID` and `TWINFIELD_CONNECT_CLIENT_SECRET` (Twinfield Connect).
 * 2. The profile of `vendor/bin/twinfield login` in `~/.config/twinfield/credentials.json`: the
 *    given profile, else `TWINFIELD_PROFILE`, else `default`.
 *
 * In an application where users log in themselves, create the access token provider yourself.
 */
final class Credentials
{
    public static function default(?string $profile = null): AccessTokenProvider
    {
        $refreshToken = Env::get('TWINFIELD_REFRESH_TOKEN');

        if ($profile === null && $refreshToken !== null) {
            return new RefreshTokenAccessTokenProvider(self::environmentProvider(), $refreshToken);
        }

        $profile ??= Env::get('TWINFIELD_PROFILE') ?? 'default';

        $store = CredentialStore::default();

        if ($store->profile($profile) === null) {
            throw CredentialsException::notLoggedIn($profile);
        }

        return new ProfileAccessTokenProvider($store, $profile);
    }

    private static function environmentProvider(): AbstractTwinfieldProvider
    {
        $redirectUri = Env::get('TWINFIELD_REDIRECT_URI') ?? LoopbackLogin::REDIRECT_URI;

        $clientId = Env::get('TWINFIELD_CLIENT_ID');
        $clientSecret = Env::get('TWINFIELD_CLIENT_SECRET');

        if ($clientId !== null && $clientSecret !== null) {
            return Profile::provider($clientId, $clientSecret, $redirectUri, null);
        }

        $clientId = Env::get('TWINFIELD_CONNECT_CLIENT_ID');
        $clientSecret = Env::get('TWINFIELD_CONNECT_CLIENT_SECRET');

        if ($clientId !== null && $clientSecret !== null) {
            $connectUrl = Env::get('TWINFIELD_CONNECT_URL') ?? TwinfieldConnect::CONNECT_URL;

            return Profile::provider($clientId, $clientSecret, $redirectUri, $connectUrl);
        }

        throw new CredentialsException(
            'TWINFIELD_REFRESH_TOKEN is set, but TWINFIELD_CLIENT_ID and TWINFIELD_CLIENT_SECRET are not, '
            . 'nor TWINFIELD_CONNECT_CLIENT_ID and TWINFIELD_CONNECT_CLIENT_SECRET.',
        );
    }
}
