---
title: Your own Twinfield client
description: Log in with your own Twinfield OAuth client instead of Twinfield Connect.
---

By default `twf login` goes through [Twinfield Connect](https://github.com/solvebeam/twinfield-connect-php), so you do not need a Twinfield client ID. The first login registers the Twinfield CLI with Twinfield Connect, once per machine.

When you have your own Twinfield client, log in with it:

```sh
vendor/bin/twf login --client-id=… --client-secret=… --redirect-uri=http://localhost:8765/callback
```

The redirect URI must be registered with your client. Without `--redirect-uri` the login uses `http://localhost:8765/callback`.

## With environment variables

Instead of the options you can set these variables:

| Variable | Option |
|---|---|
| `TWINFIELD_CLIENT_ID` | `--client-id` |
| `TWINFIELD_CLIENT_SECRET` | `--client-secret` |
| `TWINFIELD_REDIRECT_URI` | `--redirect-uri` |

An option wins over its variable.

## A Twinfield Connect you run yourself

```sh
vendor/bin/twf login --connect-url=https://connect.example.com
```

Or set `TWINFIELD_CONNECT_URL`.

## After the login

The client is stored with the profile, so `twf token`, `twf office list` and `Credentials::default()` keep using it. [`twf status`](/manual/twf_status/) shows the client of each profile.
