<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Cli\Command;

use DateTimeImmutable;
use InvalidArgumentException;
use SolveBeam\Twinfield\Cli\CredentialsException;
use SolveBeam\Twinfield\DeletedTransaction\DeletedTransaction;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `twf deleted-transaction list`: the transactions that were deleted in an office, of all day books.
 *
 * @see https://developers.twinfield.com/documentation/api/transaction-data/deleted-transactions/
 */
#[AsCommand(name: 'deleted-transaction:list', description: 'List the deleted transactions')]
final class DeletedTransactionListCommand extends Command
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption('office', null, InputOption::VALUE_REQUIRED, 'The code of the office (company)')
            ->addOption('day-book', null, InputOption::VALUE_REQUIRED, 'Only this day book, e.g. BNK')
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'Deleted on or after this date, e.g. 2026-11-01')
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'Deleted on or before this date, e.g. 2026-11-30')
            ->addOption('limit', 'L', InputOption::VALUE_REQUIRED, 'The most transactions to list (default: all)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output JSON')
            ->setHelp(implode(PHP_EOL, [
                'Lists the deleted transactions of an office, the newest deletion first. In a terminal they',
                'are a table; in a pipe they are tab-separated lines without a header: day book, number,',
                'transaction date, moment of deletion, user and reason.',
                '',
                '  ' . CredentialsException::command('deleted-transaction list --office=1000 --from=2026-11-01'),
                '  ' . CredentialsException::command('deleted-transaction list --office=1000 --day-book=BNK -L 10'),
                '  ' . CredentialsException::command('deleted-transaction list --office=1000 --json'),
                '',
                'Twinfield has no paging here: without --from you get all deleted transactions of the',
                'office, which can be thousands.',
            ]));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $office = $this->option($input, 'office') ?? throw new InvalidArgumentException(sprintf(
            'No office. Give the code of the office: %s',
            CredentialsException::command('deleted-transaction list --office=1000'),
        ));

        $limit = $this->limit($input);
        $from = $this->date($input, 'from');
        $to = $this->date($input, 'to');

        $transactions = $this->twinfield($input)->company($office)->deletedTransactions()->list(
            dayBook: $this->option($input, 'day-book'),
            from: $from,
            to: $to,
        );

        if ($limit !== null) {
            // Twinfield has no paging: the list is cut here.
            $transactions = array_slice($transactions, 0, $limit);
        }

        $json = $input->getOption('json') === true;

        if (!$json && $this->isTerminal($output)) {
            self::table($transactions, $output);

            return self::SUCCESS;
        }

        // Also with --quiet: the list is the result of the command, e.g. in a pipe.
        $output->writeln(
            $json ? self::json($transactions) : array_map(self::line(...), self::rows($transactions)),
            OutputInterface::OUTPUT_RAW | OutputInterface::VERBOSITY_QUIET,
        );

        return self::SUCCESS;
    }

    /**
     * The date of an option, written as 2026-11-04; null when the option is not given.
     */
    private function date(InputInterface $input, string $name): ?DateTimeImmutable
    {
        $value = $this->option($input, $name);

        if ($value === null) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        // 2026-02-31 would be read as 3 March.
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException(sprintf('--%s must be a date like 2026-11-04.', $name));
        }

        return $date;
    }

    /**
     * @param list<DeletedTransaction> $transactions
     */
    private static function json(array $transactions): string
    {
        return json_encode(
            array_map(static fn (DeletedTransaction $transaction): array => [
                'office' => $transaction->company->code,
                'dayBook' => $transaction->dayBook,
                'number' => $transaction->number,
                'transactionDate' => $transaction->transactionDate->format('Y-m-d'),
                // Without a time zone: Twinfield gives none.
                'deletionDate' => $transaction->deletionDate->format('Y-m-d\TH:i:s.v'),
                'user' => $transaction->user,
                'reason' => $transaction->reason,
            ], $transactions),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * The deleted transactions as a table, for a terminal.
     *
     * @param list<DeletedTransaction> $transactions
     */
    private static function table(array $transactions, OutputInterface $output): void
    {
        $table = new Table($output);

        $table->setStyle('compact');
        $table->setHeaders(['Day book', 'Number', 'Date', 'Deleted', 'User', 'Reason']);

        foreach (self::rows($transactions) as $row) {
            // A table is written with formatting: a `<` in a reason is not a tag.
            $table->addRow(array_map(OutputFormatter::escape(...), $row));
        }

        $table->render();
    }

    /**
     * @param list<DeletedTransaction> $transactions
     *
     * @return list<list<string>>
     */
    private static function rows(array $transactions): array
    {
        return array_map(
            static fn (DeletedTransaction $transaction): array => [
                $transaction->dayBook,
                (string) $transaction->number,
                $transaction->transactionDate->format('Y-m-d'),
                $transaction->deletionDate->format('Y-m-d H:i:s'),
                $transaction->user,
                $transaction->reason,
            ],
            $transactions,
        );
    }
}
