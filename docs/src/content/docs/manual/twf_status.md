---
title: twf status
description: Show the Twinfield logins stored on this machine.
---

Show the logins.

```sh
twf status [options]
```

`twf status` shows each profile in the [credentials file](/guides/credentials/): the organisation, the user, the cluster, the client and whether the access token is still valid. It marks the profile in use and prints where the file is.

It fails when there is no login, so you can use it in a script to check for one.

When `TWINFIELD_REFRESH_TOKEN` is set, the command says so: `Credentials::default()` then uses that token instead of a profile, see [CI and environment variables](/guides/ci/).

## Options

| Option | Description |
|---|---|
| `--profile=<name>` | Show only this profile. Default: all profiles. |

See [`twf`](/manual/twf/) for the options every command takes.

## Examples

```sh
vendor/bin/twf status
```

```text
default (in use)
  Organisation:  EXAMPLE
  User:          JDOE
  Cluster:       https://api.accounting.twinfield.com
  Client:        Twinfield Connect (https://connect.twinfield.solvebeam.com)
  Access token:  valid until 2026-11-12 15:04:05 UTC

Saved in /home/jdoe/.config/twinfield/credentials.json
```

Only one profile:

```sh
vendor/bin/twf status --profile=acme
```

## See also

- [`twf login`](/manual/twf_login/)
- [`twf token`](/manual/twf_token/)
