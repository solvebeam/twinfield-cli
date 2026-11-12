---
title: Scripting
description: Use the output of twf in pipes and scripts, with tab-separated lines, JSON and exit statuses.
---

The commands of `twf` are made to be used in scripts, like those of `gh`.

## Output in a terminal and in a pipe

A command that prints a list looks where its output goes:

- In a terminal it prints a table with a header, for people.
- In a pipe or a file it prints tab-separated lines without a header, for a script.

```sh
vendor/bin/twf office list | cut -f1
```

[`twf process-xml`](/manual/twf_process-xml/) does the same with XML: indented in a terminal, as Twinfield returned it in a pipe.

## JSON

A list command takes `--json` for the same rows as JSON, for example for [jq](https://jqlang.org/):

```sh
vendor/bin/twf office list --json | jq -r '.[] | select(.country == "NL") | .code'
```

## Exit status

| Status | Meaning |
|---|---|
| `0` | The command succeeded. |
| `1` | The command failed. |

An error is one line on standard error:

```text
Error: Not logged in to Twinfield. Run: vendor/bin/twf login
```

Add `-v` for the trace of the error.

## The result is always printed

The result of a command, such as the access token of `twf token` or the lines of `twf office list`, goes to standard output, also with `--quiet`. Messages for people are left out with `--quiet`.

```sh
token=$(vendor/bin/twf token)
```

## Check for a login

`twf status` fails when there is no login:

```sh
if ! vendor/bin/twf status --quiet; then
    vendor/bin/twf login
fi
```
