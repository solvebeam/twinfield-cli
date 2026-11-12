---
title: Profiles
description: Keep a Twinfield login for each organisation with profiles.
---

A profile is a stored login with a name. Use a profile for each Twinfield organisation you work with. Without a name, the commands use the profile `default`.

## Log in to a profile

```sh
vendor/bin/twf login --profile=acme
```

[`twf status`](/manual/twf_status/) shows all profiles and marks the one in use.

## Choose a profile

Every command takes `--profile`:

```sh
vendor/bin/twf office list --profile=acme
```

Or set `TWINFIELD_PROFILE` for all commands in a shell or a script:

```sh
export TWINFIELD_PROFILE=acme

vendor/bin/twf office list
```

The profile is, in this order: the `--profile` option, `TWINFIELD_PROFILE`, `default`.

## In PHP

```php
use SolveBeam\Twinfield\Cli\Credentials;
use SolveBeam\Twinfield\Twinfield;

$twinfield = Twinfield::create(Credentials::default('acme'));
```

Without a name, `Credentials::default()` uses `TWINFIELD_PROFILE`, else `default`.

## Remove a profile

```sh
vendor/bin/twf logout --profile=acme
```
