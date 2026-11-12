<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Cli\Command;

use InvalidArgumentException;
use SolveBeam\Twinfield\Cli\CredentialsException;
use SolveBeam\Twinfield\Office\OfficeSummary;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `twf office list`: the offices (companies, administrations) the logged in user has access to.
 */
#[AsCommand(name: 'office:list', description: 'List the offices')]
final class OfficeListCommand extends Command
{
    /** The most rows the Finder of Twinfield returns in one request. */
    private const int PAGE_SIZE = 100;

    protected function configure(): void
    {
        parent::configure();

        $this
            ->addArgument(
                'pattern',
                InputArgument::OPTIONAL,
                'Search in code and name: * matches anything, ? one character',
                '*',
            )
            ->addOption('limit', 'L', InputOption::VALUE_REQUIRED, 'The most offices to list (default: all)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output JSON')
            ->setHelp(implode(PHP_EOL, [
                'In a terminal the offices are a table; in a pipe they are tab-separated lines without a',
                'header: code, name, country and company ID.',
                '',
                '  ' . CredentialsException::command('office list'),
                '  ' . CredentialsException::command("office list '*Holding*' --limit=10"),
                '  ' . CredentialsException::command('office list --json'),
                '  ' . CredentialsException::command('office list --profile=acme'),
            ]));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = $this->limit($input);
        $pattern = $input->getArgument('pattern');

        $search = $this->twinfield($input)->offices()->search(is_string($pattern) && $pattern !== '' ? $pattern : '*');

        if ($limit !== null) {
            $search = $search->limit(min($limit, self::PAGE_SIZE));
        }

        $offices = [];

        foreach ($search->all() as $office) {
            $offices[] = $office;

            if (count($offices) === $limit) {
                break;
            }
        }

        $json = $input->getOption('json') === true;

        if (!$json && $this->isTerminal($output)) {
            self::table($offices, $output);

            return self::SUCCESS;
        }

        // Also with --quiet: the list is the result of the command, e.g. in a pipe.
        $output->writeln(
            $json ? self::json($offices) : self::lines($offices),
            OutputInterface::OUTPUT_RAW | OutputInterface::VERBOSITY_QUIET,
        );

        return self::SUCCESS;
    }

    private function limit(InputInterface $input): ?int
    {
        $limit = $input->getOption('limit');

        if ($limit === null) {
            return null;
        }

        if (!is_string($limit) || !ctype_digit($limit) || (int) $limit < 1) {
            throw new InvalidArgumentException('--limit must be a number of 1 or more.');
        }

        return (int) $limit;
    }

    /**
     * @param list<OfficeSummary> $offices
     */
    private static function json(array $offices): string
    {
        return json_encode(
            array_map(static fn (OfficeSummary $office): array => [
                'code' => $office->code,
                'name' => $office->name,
                'companyId' => $office->companyId,
                'country' => $office->country,
                'vatPeriod' => $office->vatPeriod,
                'startingQuarter' => $office->startingQuarter,
            ], $offices),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * The offices as a table, for a terminal.
     *
     * @param list<OfficeSummary> $offices
     */
    private static function table(array $offices, OutputInterface $output): void
    {
        $table = new Table($output);

        $table->setStyle('compact');
        $table->setHeaders(['Code', 'Name', 'Country', 'Company ID']);

        foreach (self::rows($offices) as $row) {
            // A table is written with formatting: a `<` in a name is not a tag.
            $table->addRow(array_map(OutputFormatter::escape(...), $row));
        }

        $table->render();
    }

    /**
     * The offices as tab-separated lines without a header, for a pipe: `twf office list | cut -f1`.
     *
     * @param list<OfficeSummary> $offices
     *
     * @return list<string>
     */
    private static function lines(array $offices): array
    {
        return array_map(
            static fn (array $row): string => implode("\t", array_map(
                // A tab or a line break in a value would start a new column or line.
                static fn (string $value): string => trim((string) preg_replace('/[\t\r\n]+/', ' ', $value)),
                $row,
            )),
            self::rows($offices),
        );
    }

    /**
     * @param list<OfficeSummary> $offices
     *
     * @return list<list<string>>
     */
    private static function rows(array $offices): array
    {
        return array_map(
            static fn (OfficeSummary $office): array => [
                $office->code,
                $office->name,
                $office->country,
                $office->companyId,
            ],
            $offices,
        );
    }
}
