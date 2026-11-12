---
title: twf office list
description: List the offices you have access to in Twinfield.
---

List the offices.

```sh
twf office list [<pattern>] [options]
```

`twf office list` lists the offices (companies, administrations) the logged in user has access to, like `gh repo list`.

In a terminal the offices are a table. In a pipe they are tab-separated lines without a header: code, name, country and company ID.

## Arguments

| Argument | Description |
|---|---|
| `<pattern>` | Search in code and name: `*` matches anything, `?` one character. Default: `*`, all offices. Quote the pattern, so your shell does not expand it. |

## Options

| Option | Description |
|---|---|
| `-L`, `--limit=<number>` | The most offices to list. Default: all. |
| `--json` | Output JSON: a list of objects with `code`, `name`, `companyId`, `country`, `vatPeriod` and `startingQuarter`. |
| `--profile=<name>` | The login to use, see [Profiles](/guides/profiles/). Default: `TWINFIELD_PROFILE` or `default`. |

See [`twf`](/manual/twf/) for the options every command takes.

## Examples

All offices:

```sh
vendor/bin/twf office list
```

```text
Code Name                 Country Company ID
1000 Example B.V.         NL      FFE2C8B3-E7F0-4793-A059-FFBFE3C154A9
2000 Example Holding B.V. BE      0a1b2c3d-1111-2222-3333-444455556666
```

Search in code and name:

```sh
vendor/bin/twf office list '*Holding*'
```

The first 10 offices:

```sh
vendor/bin/twf office list --limit=10
```

Only the codes, in a pipe:

```sh
vendor/bin/twf office list | cut -f1
```

As JSON, for example for jq:

```sh
vendor/bin/twf office list --json | jq -r '.[].code'
```

The offices of another login:

```sh
vendor/bin/twf office list --profile=acme
```

## See also

- [`twf process-xml`](/manual/twf_process-xml/): takes the code of an office with `--office`.
- [Scripting](/guides/scripting/)
