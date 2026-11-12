---
title: Where the login is stored
description: The credentials file of the Twinfield CLI, its location and how tokens are refreshed.
---

`twf login` stores the login in one file, `credentials.json`.

## Location

The first of these that applies:

| When | File |
|---|---|
| `TWINFIELD_CONFIG_DIR` is set | `$TWINFIELD_CONFIG_DIR/credentials.json` |
| `XDG_CONFIG_HOME` is set | `$XDG_CONFIG_HOME/twinfield/credentials.json` |
| Otherwise | `~/.config/twinfield/credentials.json` |

[`twf status`](/manual/twf_status/) prints the path of the file in use.

## What is in it

- For each [profile](/guides/profiles/): the client, the refresh token, the last access token and when it expires, and the organisation, user and cluster of the login.
- The client the Twinfield CLI registered with Twinfield Connect on this machine.

Only you can read the file: it has mode `0600`, and its directory is created with mode `0700`.

:::caution
The file holds refresh tokens and client secrets. Do not commit it and do not share it.
:::

## How tokens are refreshed

`Credentials::default()` and the commands use the stored access token until it expires. Then they get a new one with the refresh token and write the new tokens back to the file.

Writes lock the file. Processes that run at the same time, such as parallel tests, do not overwrite each other's refresh token.

## Remove the login

[`twf logout`](/manual/twf_logout/) removes a profile from the file.
