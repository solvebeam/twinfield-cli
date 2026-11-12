---
title: twf deleted-transaction list
description: List the transactions that were deleted in an office in Twinfield.
---

List the deleted transactions.

```sh
twf deleted-transaction list --office=<code> [options]
```

`twf deleted-transaction list` lists the transactions that were deleted in an office, of all day books, the newest deletion first. Each one has its day book, number, transaction date, the moment it was deleted, the user who deleted it and the reason. Transactions deleted in Twinfield itself are listed too, so you can use it to keep a copy of the transactions in sync.

In a terminal the deleted transactions are a table. In a pipe they are tab-separated lines without a header: day book, number, transaction date, moment of deletion, user and reason.

Twinfield has no paging for deleted transactions: without `--from` you get all deleted transactions of the office, which can be thousands.

## Options

| Option | Description |
|---|---|
| `--office=<code>` | The code of the office (company). Required. |
| `--day-book=<code>` | Only the deleted transactions of this day book, for example `BNK`. Default: all day books. |
| `--from=<date>` | Deleted on or after this date, written as `2026-11-01`. |
| `--to=<date>` | Deleted on or before this date, written as `2026-11-30`. |
| `-L`, `--limit=<number>` | The most transactions to list. Default: all. |
| `--json` | Output JSON: a list of objects with `office`, `dayBook`, `number`, `transactionDate`, `deletionDate`, `user` and `reason`. |
| `--profile=<name>` | The login to use, see [Profiles](/guides/profiles/). Default: `TWINFIELD_PROFILE` or `default`. |

`--from` and `--to` are about the date of deletion, not the date of the transaction. Both days are included.

The `deletionDate` in JSON has no time zone, like `2026-11-04T05:31:52.420`: Twinfield gives none.

See [`twf`](/manual/twf/) for the options every command takes.

## Examples

The transactions deleted since 1 November:

```sh
vendor/bin/twf deleted-transaction list --office=1000 --from=2026-11-01
```

```text
Day book Number    Date       Deleted             User Reason
BNK      202600045 2026-11-02 2026-11-04 05:31:52 JDOE Booked twice
MEMO     202600012 2026-10-30 2026-11-03 16:02:10 JDOE Wrong period
```

Deleted on one day, in one day book:

```sh
vendor/bin/twf deleted-transaction list --office=1000 --day-book=BNK --from=2026-11-04 --to=2026-11-04
```

The last 10 deletions:

```sh
vendor/bin/twf deleted-transaction list --office=1000 --limit=10
```

Only the day books and numbers, in a pipe:

```sh
vendor/bin/twf deleted-transaction list --office=1000 --from=2026-11-01 | cut -f1,2
```

As JSON, for example for jq:

```sh
vendor/bin/twf deleted-transaction list --office=1000 --json | jq -r '.[] | "\(.dayBook) \(.number): \(.reason)"'
```

## See also

- [`twf office list`](/manual/twf_office_list/): the codes of the offices.
- [List deleted transactions](https://php.twinfield.solvebeam.com/examples/deleted-transactions/list-deleted-transactions/) in PHP, with the PHP Twinfield SDK.
- [Deleted transactions](https://developers.twinfield.com/documentation/api/transaction-data/deleted-transactions/) in the documentation of Twinfield.
- [Scripting](/guides/scripting/)
