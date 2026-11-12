---
title: twf logout
description: Remove a Twinfield login from the credentials file.
---

Remove a login.

```sh
twf logout [options]
```

`twf logout` removes a profile, with its tokens, from the [credentials file](/guides/credentials/). It fails when there is no login for the profile.

## Options

| Option | Description |
|---|---|
| `--profile=<name>` | The profile to remove, see [Profiles](/guides/profiles/). Default: `TWINFIELD_PROFILE` or `default`. |

See [`twf`](/manual/twf/) for the options every command takes.

## Examples

```sh
vendor/bin/twf logout
```

```text
Logged out of Twinfield (profile "default").
```

Remove the login of another organisation:

```sh
vendor/bin/twf logout --profile=acme
```

## See also

- [`twf login`](/manual/twf_login/)
- [`twf status`](/manual/twf_status/)
