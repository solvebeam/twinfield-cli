---
title: twf login
description: Log in to Twinfield with your browser and store the login.
---

Log in with your browser.

```sh
twf login [options]
```

`twf login` opens the Twinfield login in your browser and catches the redirect on `http://localhost:8765/callback`. It waits up to 5 minutes. After the login it stores the tokens in the [credentials file](/guides/credentials/), under the profile you chose.

By default the login goes through [Twinfield Connect](https://github.com/solvebeam/twinfield-connect-php), so you do not need a Twinfield client ID. The first login registers the Twinfield CLI with Twinfield Connect, once per machine.

A login for a profile that already exists replaces it.

## Options

| Option | Description |
|---|---|
| `--profile=<name>` | The profile to store the login under, see [Profiles](/guides/profiles/). Default: `TWINFIELD_PROFILE` or `default`. |
| `--manual` | Do not open a browser and do not listen on localhost. The command prints the login URL and asks you to paste the URL your browser was redirected to. For a machine without a browser, e.g. over SSH. |
| `--rest` | Also ask for all scopes of the REST API. |
| `--scopes=<scopes>` | Ask for exactly these scopes, separated by spaces: `--scopes="openid twf.user"`. |
| `--client-id=<id>` | [Your own Twinfield client](/guides/own-client/) instead of Twinfield Connect. Default: `TWINFIELD_CLIENT_ID`. |
| `--client-secret=<secret>` | The secret of your own client. Default: `TWINFIELD_CLIENT_SECRET`. |
| `--redirect-uri=<uri>` | The redirect URI of the login. Default: `TWINFIELD_REDIRECT_URI` or `http://localhost:8765/callback`. |
| `--connect-url=<url>` | A Twinfield Connect you run yourself. Default: `TWINFIELD_CONNECT_URL`. |

See [`twf`](/manual/twf/) for the options every command takes.

## Scopes

Without `--rest` or `--scopes` the login asks for the scopes that identify you and keep the session: `openid`, `twf.user`, `twf.organisation`, `twf.organisationUser` and `offline_access`. These are enough for the XML web services, such as [`twf process-xml`](/manual/twf_process-xml/) and [`twf office list`](/manual/twf_office_list/).

## Examples

Log in with your browser:

```sh
vendor/bin/twf login
```

```text
Opening the Twinfield login in your browser…
If it does not open, visit: https://…
Waiting for the login on http://localhost:8765/callback (max 300 seconds)…

Logged in to Twinfield.
  Organisation:  EXAMPLE
  User:          JDOE
  Profile:       default
  Saved in:      /home/jdoe/.config/twinfield/credentials.json

Use it in PHP:
  $twinfield = Twinfield::create(Credentials::default());
```

Over SSH, paste the redirected URL yourself:

```sh
vendor/bin/twf login --manual
```

A login for another organisation:

```sh
vendor/bin/twf login --profile=acme
```

With all scopes of the REST API:

```sh
vendor/bin/twf login --rest
```

With your own Twinfield client:

```sh
vendor/bin/twf login --client-id=… --client-secret=… --redirect-uri=http://localhost:8765/callback
```

## See also

- [`twf status`](/manual/twf_status/)
- [`twf logout`](/manual/twf_logout/)
