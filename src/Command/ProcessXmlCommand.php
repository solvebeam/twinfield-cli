<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Cli\Command;

use DOMDocument;
use InvalidArgumentException;
use SolveBeam\Twinfield\Cli\CredentialsException;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `twf process-xml`, or `twf xml`: a request on the XML web services of Twinfield (ProcessXml), like
 * `gh api`.
 *
 * @see https://developers.twinfield.com/documentation/api/types/xml-web-services/
 */
#[AsCommand(
    name: 'process-xml',
    description: 'Send an XML request to Twinfield (ProcessXml)',
    aliases: ['xml'],
)]
final class ProcessXmlCommand extends Command
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addArgument('file', InputArgument::OPTIONAL, 'The file with the XML request (default: standard input)')
            ->addOption('office', null, InputOption::VALUE_REQUIRED, 'The code of the office (company) of the request')
            ->setHelp(implode(PHP_EOL, [
                'Reads, creates or changes anything the XML web services of Twinfield support. The response',
                'is printed as Twinfield returned it; in a terminal it is indented.',
                '',
                '  ' . CredentialsException::command('process-xml request.xml --office=1000'),
                "  echo '<read><type>office</type><code>1000</code></read>' | "
                    . CredentialsException::command('process-xml --office=1000'),
                '',
                'When Twinfield does not process (all of) the request, the response is printed too: it has',
                'the messages (msg) on the elements they are about. The exit status is then 1.',
            ]));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $request = $this->request($input);

        $twinfield = $this->twinfield($input);

        $office = $this->option($input, 'office');

        if ($office !== null) {
            $twinfield = $twinfield->company($office);
        }

        try {
            $result = $twinfield->processXml($request);
        } catch (ProcessXmlException $exception) {
            // The response says what is wrong, and where.
            $this->writeXml($exception->result->xml(), $output);

            throw $exception;
        }

        $this->writeXml($result->xml(), $output);

        return self::SUCCESS;
    }

    private function request(InputInterface $input): string
    {
        $file = $input->getArgument('file');

        if (is_string($file) && $file !== '' && $file !== '-') {
            $request = is_file($file) ? file_get_contents($file) : false;

            if ($request === false) {
                throw new InvalidArgumentException(sprintf('Cannot read the file "%s".', $file));
            }

            return $request;
        }

        $stream = ($input instanceof StreamableInputInterface ? $input->getStream() : null) ?? STDIN;

        $request = stream_isatty($stream) ? '' : (string) stream_get_contents($stream);

        if (trim($request) === '') {
            throw new InvalidArgumentException(sprintf(
                'No XML request. Give a file or pipe the XML: %s',
                CredentialsException::command('process-xml request.xml'),
            ));
        }

        return $request;
    }

    private function writeXml(string $xml, OutputInterface $output): void
    {
        if ($this->isTerminal($output)) {
            $document = new DOMDocument();
            $document->preserveWhiteSpace = false;
            $document->formatOutput = true;

            if (@$document->loadXML($xml, LIBXML_NONET | LIBXML_PARSEHUGE) && $document->documentElement !== null) {
                $xml = (string) $document->saveXML($document->documentElement);
            }
        }

        // Also with --quiet: the response is the result of the command.
        $output->writeln($xml, OutputInterface::OUTPUT_RAW | OutputInterface::VERBOSITY_QUIET);
    }
}
