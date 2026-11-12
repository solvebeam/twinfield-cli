---
title: twf token
description: Print a valid Twinfield access token, for example for curl.
---

Print a valid access token.

```sh
twf token [options]
```

`twf token` prints the access token of a profile on standard output, and nothing else. When the stored token has expired, the command first gets a new one and stores it.

:::caution
An access token gives access to your Twinfield organisation until it expires. Do not paste it in an issue, a chat or a log.
:::

## Options

| Option | Description |
|---|---|
| `--profile=<name>` | The login to use, see [Profiles](/guides/profiles/). Default: `TWINFIELD_PROFILE` or `default`. |

See [`twf`](/manual/twf/) for the options every command takes.

## Examples

Use the token in a request with curl:

```sh
curl --header "Authorization: Bearer $(vendor/bin/twf token)" "$url"
```

Keep it in a variable:

```sh
token=$(vendor/bin/twf token --profile=acme)
```

## See also

- [`twf status`](/manual/twf_status/)
- [`twf process-xml`](/manual/twf_process-xml/): send an XML request without handling the token yourself.
