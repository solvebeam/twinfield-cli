---
title: CI and environment variables
description: Use the Twinfield CLI in CI with a refresh token in environment variables.
---

In CI there is no browser to log in with. Give the login with environment variables instead.

## A refresh token instead of the file

When `TWINFIELD_REFRESH_TOKEN` is set, `Credentials::default()` uses it instead of the credentials file. It also needs the client the refresh token belongs to:

- `TWINFIELD_CONNECT_CLIENT_ID` and `TWINFIELD_CONNECT_CLIENT_SECRET` for a login through Twinfield Connect, or
- `TWINFIELD_CLIENT_ID` and `TWINFIELD_CLIENT_SECRET` for [your own Twinfield client](/guides/own-client/).

You find these values in [`credentials.json`](/guides/credentials/) after a `twf login` on your own machine. Store them as secrets in your CI.

Tokens from environment variables are not written back to a file.

:::note
`Credentials::default('acme')` always uses the profile you name, also when `TWINFIELD_REFRESH_TOKEN` is set.

The commands of `twf` always use a profile from the credentials file. `TWINFIELD_REFRESH_TOKEN` is for `Credentials::default()` in PHP.
:::

## Variables

| Variable | Use |
|---|---|
| `TWINFIELD_REFRESH_TOKEN` | The refresh token. |
| `TWINFIELD_CONNECT_CLIENT_ID`, `TWINFIELD_CONNECT_CLIENT_SECRET` | The Twinfield Connect client, see `credentials.json`. |
| `TWINFIELD_CLIENT_ID`, `TWINFIELD_CLIENT_SECRET` | Or your own Twinfield client. |
| `TWINFIELD_CONNECT_URL` | Only for a Twinfield Connect you run yourself. |
| `TWINFIELD_REDIRECT_URI` | Only when your client does not use `http://localhost:8765/callback`. |
| `TWINFIELD_PROFILE` | The [profile](/guides/profiles/) to use instead of `default`. |
| `TWINFIELD_CONFIG_DIR` | The directory of [`credentials.json`](/guides/credentials/). |

## Example: GitHub Actions

```yaml
- run: composer phpunit
  env:
    TWINFIELD_REFRESH_TOKEN: ${{ secrets.TWINFIELD_REFRESH_TOKEN }}
    TWINFIELD_CONNECT_CLIENT_ID: ${{ secrets.TWINFIELD_CONNECT_CLIENT_ID }}
    TWINFIELD_CONNECT_CLIENT_SECRET: ${{ secrets.TWINFIELD_CONNECT_CLIENT_SECRET }}
```
