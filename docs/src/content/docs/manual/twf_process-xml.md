---
title: twf process-xml
description: Send an XML request to the XML web services of Twinfield (ProcessXml).
---

Send an XML request to Twinfield (ProcessXml).

```sh
twf process-xml [<file>] [options]
```

`twf process-xml` sends a request to the [XML web services](https://developers.twinfield.com/documentation/api/types/xml-web-services/) of Twinfield, like `gh api`. With it you read, create and change anything these web services support.

`twf xml` is the short name of `twf process-xml`.

The request comes from a file or from standard input. The response is printed as Twinfield returned it; in a terminal it is indented.

## Arguments

| Argument | Description |
|---|---|
| `<file>` | The file with the XML request. Default: standard input. `-` also reads standard input. |

## Options

| Option | Description |
|---|---|
| `--office=<code>` | The code of the office (company) the request is for. Find the codes with [`twf office list`](/manual/twf_office_list/). |
| `--profile=<name>` | The login to use, see [Profiles](/guides/profiles/). Default: `TWINFIELD_PROFILE` or `default`. |

See [`twf`](/manual/twf/) for the options every command takes.

## Errors

When Twinfield does not process the request, or not all of it, the response is printed too: it has the messages (`msg`) on the elements they are about. The error goes to standard error and the exit status is 1.

## Examples

A request from a file:

```sh
vendor/bin/twf process-xml request.xml --office=1000
```

A request from standard input:

```sh
echo '<read><type>office</type><code>1000</code></read>' | vendor/bin/twf process-xml --office=1000
```

With the short name:

```sh
vendor/bin/twf xml request.xml --office=1000
```

Save the response in a file:

```sh
vendor/bin/twf process-xml request.xml --office=1000 > response.xml
```

## See also

- [XML web services (ProcessXml)](https://php.twinfield.solvebeam.com/guides/process-xml/) in the PHP Twinfield SDK
- [`twf token`](/manual/twf_token/)
