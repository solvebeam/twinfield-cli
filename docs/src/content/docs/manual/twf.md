---
title: twf
description: The twf command, its commands and the options every command takes.
---

Twinfield on the command line.

```sh
twf <command> [<arguments>] [options]
```

The command is `twf`, after the `twf.` prefix Twinfield uses itself (`twf.organisationCode`). `twinfield` is the same command under its long name. Installed with Composer, you call it as `vendor/bin/twf`.

## Commands

| Command | Description |
|---|---|
| [`twf login`](/manual/twf_login/) | Log in with your browser |
| [`twf logout`](/manual/twf_logout/) | Remove a login |
| [`twf office list`](/manual/twf_office_list/) | List the offices |
| [`twf process-xml`](/manual/twf_process-xml/), `twf xml` | Send an XML request to Twinfield (ProcessXml) |
| [`twf status`](/manual/twf_status/) | Show the logins |
| [`twf token`](/manual/twf_token/) | Print a valid access token |

A command in a group is written with a space, like `gh repo list`: `twf office list`.

## Help

| Command | Description |
|---|---|
| `twf` or `twf list` | Show all commands |
| `twf <command> --help` | Show the arguments and options of a command |
| `twf help <command>` | The same |
| `twf office` | Show the commands of a group |
| `twf completion` | Print the shell completion script for bash, fish or zsh |

## Options of every command

| Option | Description |
|---|---|
| `--profile=<name>` | The login to use, see [Profiles](/guides/profiles/). Default: `TWINFIELD_PROFILE` or `default`. |
| `-h`, `--help` | Show the help of the command. |
| `-q`, `--quiet` | Print only the result of the command and errors. |
| `--silent` | Print nothing. |
| `-v`, `-vv`, `-vvv`, `--verbose` | Print more; with `-v` an error has its trace. |
| `-V`, `--version` | Print the version of the Twinfield CLI. |
| `--ansi`, `--no-ansi` | Force or disable colours. |
| `-n`, `--no-interaction` | Do not ask questions. |

## Examples

```sh
vendor/bin/twf login
vendor/bin/twf status
vendor/bin/twf office list
vendor/bin/twf login --help
```

## See also

- [Scripting](/guides/scripting/)
- [CI and environment variables](/guides/ci/)
