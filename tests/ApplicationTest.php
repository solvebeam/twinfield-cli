<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Cli\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use SolveBeam\Twinfield\Cli\Application;
use SolveBeam\Twinfield\Cli\CredentialsException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Tester\ApplicationTester;

final class ApplicationTest extends TestCase
{
    private const array OFFICES = [
        ['1000', 'Example B.V.', 'FFE2C8B3-E7F0-4793-A059-FFBFE3C154A9', 'NL', '1', '21'],
        ['2000', 'Example <Holding> B.V.', '0a1b2c3d-1111-2222-3333-444455556666', 'BE', '3', '21'],
    ];

    /** @var list<Response> The responses of Twinfield, in order. */
    private array $responses = [];

    /** @var list<string> The bodies of the requests to Twinfield. */
    private array $requests = [];

    public function testHelpWithoutACommand(): void
    {
        [$status, $output, $error] = $this->twf();

        self::assertSame(0, $status);
        self::assertStringContainsString('Twinfield CLI brings Twinfield to your terminal.', $output);
        self::assertMatchesRegularExpression('/^  login +Log in with your browser$/m', $output);
        self::assertSame('', $error);
    }

    public function testHelpOptionShowsTheHelpOfTheCommand(): void
    {
        [$status, $output] = $this->twf('login', '--help');

        self::assertSame(0, $status);
        self::assertStringContainsString('login [options]', $output);
        self::assertStringContainsString('--manual', $output);
        self::assertStringContainsString('--profile=PROFILE', $output);
    }

    public function testUnknownCommand(): void
    {
        [$status, $output, $error] = $this->twf('foo');

        self::assertSame(1, $status);
        self::assertSame('', $output);
        self::assertStringContainsString('Command "foo" is not defined.', $error);
    }

    public function testUnknownOption(): void
    {
        [$status, , $error] = $this->twf('status', '--foo');

        self::assertSame(1, $status);
        self::assertStringContainsString('The "--foo" option does not exist.', $error);
    }

    public function testAnEmptyProfileIsTheDefaultProfile(): void
    {
        [$status, , $error] = $this->twf('token', '--profile=');

        self::assertSame(1, $status);
        self::assertStringContainsString('Not logged in to Twinfield. Run: vendor/bin/twf login', $error);
    }

    public function testStatusWhenNotLoggedIn(): void
    {
        [$status, , $error] = $this->twf('status');

        self::assertSame(1, $status);
        self::assertStringContainsString('Not logged in to Twinfield', $error);
    }

    public function testStatusShowsTheProfiles(): void
    {
        $this->store()->saveProfile('default', self::profile());
        $this->store()->saveProfile('acme', self::profile(organisationCode: 'ACME'));

        [$status, $output] = $this->twf('status');

        self::assertSame(0, $status);
        self::assertStringContainsString('default (in use)', $output);
        self::assertStringContainsString('Organisation:  ACME', $output);
        self::assertStringContainsString('valid until', $output);
    }

    public function testStatusOfOneProfile(): void
    {
        $this->store()->saveProfile('default', self::profile());
        $this->store()->saveProfile('acme', self::profile(organisationCode: 'ACME'));

        [$status, $output] = $this->twf('status', '--profile=acme');

        self::assertSame(0, $status);
        self::assertStringNotContainsString('EXAMPLE', $output);
    }

    public function testTokenPrintsTheAccessToken(): void
    {
        $this->store()->saveProfile('default', self::profile());

        [$status, $output] = $this->twf('token');

        self::assertSame(0, $status);
        self::assertSame('access-token' . PHP_EOL, $output);
    }

    public function testLogout(): void
    {
        $this->store()->saveProfile('acme', self::profile());

        [$status, $output] = $this->twf('logout', '--profile=acme');

        self::assertSame(0, $status);
        self::assertStringContainsString('profile "acme"', $output);
        self::assertNull($this->store()->profile('acme'));

        [$status, , $error] = $this->twf('logout', '--profile=acme');

        self::assertSame(1, $status);
        self::assertStringContainsString('login --profile=acme', $error);
    }

    public function testLoginRefusesASecretWithoutAClientId(): void
    {
        [$status, , $error] = $this->twf('login', '--client-secret=secret');

        self::assertSame(1, $status);
        self::assertStringContainsString('--client-id', $error);
    }

    public function testManualLoginNamesTheRedirectUriAndChecksTheState(): void
    {
        [$status, $output, $error] = $this->twfWithInput(
            'http://localhost:9000/callback?code=abc&state=not-the-state' . PHP_EOL,
            'login',
            '--manual',
            '--client-id=client-id',
            '--client-secret=client-secret',
            '--redirect-uri=http://localhost:9000/callback',
        );

        self::assertSame(1, $status);
        self::assertStringContainsString('Open the Twinfield login in your browser: https://', $output);
        self::assertStringContainsString('your browser goes to http://localhost:9000/callback', $output);
        self::assertStringContainsString('does not belong to this login', $error);
        self::assertNull($this->store()->profile('default'));
    }

    public function testManualLoginReportsTheErrorOfTwinfield(): void
    {
        [$status, , $error] = $this->twfWithInput(
            'http://localhost:8765/callback?error=access_denied&error_description=No' . PHP_EOL,
            'login',
            '--manual',
            '--client-id=client-id',
            '--client-secret=client-secret',
        );

        self::assertSame(1, $status);
        self::assertStringContainsString('Twinfield returned "access_denied": No', $error);
    }

    public function testMessagesNameTheCommandAsItWasCalled(): void
    {
        [, , $error] = $this->twfAs('vendor/bin/twf', 'logout', '--profile=acme');

        self::assertStringContainsString('Run: vendor/bin/twf login --profile=acme', $error);

        [, , $error] = $this->twfAs('bin/twinfield', 'status');

        self::assertStringContainsString('Run: bin/twinfield login', $error);
    }

    public function testHelpNamesTheCommandAsItWasCalled(): void
    {
        [, $output] = $this->twfAs('vendor/bin/twf');

        self::assertStringContainsString('`twinfield` is the long name of `twf`', $output);

        [, $output] = $this->twfAs('vendor/bin/twinfield', 'list');

        self::assertStringContainsString('`twf` is the short name of `twinfield`', $output);
    }

    public function testACommandOnThePathIsNamedWithoutItsDirectory(): void
    {
        [, , $error] = $this->twfAs('/home/jdoe/.composer/vendor/bin/twf', 'status');

        self::assertStringContainsString('Run: twf login', $error);

        [, , $error] = $this->twfAs('C:\\Users\\jdoe\\vendor\\bin\\twf', 'status');

        self::assertStringContainsString('Run: twf login', $error);
    }

    public function testOfficeListPrintsTabSeparatedLinesInAPipe(): void
    {
        $this->store()->saveProfile('default', self::profile());
        $this->responses[] = self::finderResponse(self::OFFICES);

        [$status, $output, $error] = $this->twf('office', 'list');

        self::assertSame('', $error);
        self::assertSame(0, $status);
        self::assertSame(
            "1000\tExample B.V.\tNL\tffe2c8b3-e7f0-4793-a059-ffbfe3c154a9" . PHP_EOL
            . "2000\tExample <Holding> B.V.\tBE\t0a1b2c3d-1111-2222-3333-444455556666" . PHP_EOL,
            $output,
        );
        self::assertCount(1, $this->requests);
        self::assertStringContainsString('<type>OFF</type>', $this->requests[0]);
        self::assertStringContainsString('<pattern>*</pattern>', $this->requests[0]);
        self::assertStringContainsString('<maxRows>100</maxRows>', $this->requests[0]);
    }

    public function testOfficeListIsAlsoTheSymfonyCommandOfficeList(): void
    {
        $this->store()->saveProfile('default', self::profile());
        $this->responses[] = self::finderResponse(self::OFFICES);

        [$status, $output] = $this->twf('office:list');

        self::assertSame(0, $status);
        self::assertStringContainsString("1000\tExample B.V.", $output);
    }

    public function testOfficeListSearchesWithAPattern(): void
    {
        $this->store()->saveProfile('default', self::profile());
        $this->responses[] = self::finderResponse([self::OFFICES[1]]);

        [$status, $output] = $this->twf('office', 'list', '*Holding*');

        self::assertSame(0, $status);
        self::assertStringStartsWith('2000', $output);
        self::assertStringContainsString('<pattern>*Holding*</pattern>', $this->requests[0]);
    }

    public function testOfficeListWithALimit(): void
    {
        $this->store()->saveProfile('default', self::profile());
        $this->responses[] = self::finderResponse([self::OFFICES[0]], totalRows: 2);

        [$status, $output] = $this->twf('office', 'list', '--limit=1');

        self::assertSame(0, $status);
        self::assertSame(1, substr_count($output, PHP_EOL));
        self::assertCount(1, $this->requests);
        self::assertStringContainsString('<maxRows>1</maxRows>', $this->requests[0]);
    }

    public function testOfficeListRefusesALimitThatIsNotANumber(): void
    {
        $this->store()->saveProfile('default', self::profile());

        foreach (['0', 'ten', '-1'] as $limit) {
            [$status, , $error] = $this->twf('office', 'list', '--limit=' . $limit);

            self::assertSame(1, $status);
            self::assertStringContainsString('--limit must be a number of 1 or more.', $error);
        }

        self::assertSame([], $this->requests);
    }

    public function testOfficeListAsJson(): void
    {
        $this->store()->saveProfile('default', self::profile());
        $this->responses[] = self::finderResponse(self::OFFICES);

        [$status, $output] = $this->twf('office', 'list', '--json');

        self::assertSame(0, $status);
        self::assertSame(
            [
                'code' => '1000',
                'name' => 'Example B.V.',
                'companyId' => 'ffe2c8b3-e7f0-4793-a059-ffbfe3c154a9',
                'country' => 'NL',
                'vatPeriod' => 1,
                'startingQuarter' => 21,
            ],
            json_decode($output, true, flags: JSON_THROW_ON_ERROR)[0],
        );
    }

    public function testOfficeListWithoutOffices(): void
    {
        $this->store()->saveProfile('default', self::profile());
        $this->responses[] = self::finderResponse([]);

        [$status, $output] = $this->twf('office', 'list');

        self::assertSame(0, $status);
        self::assertSame('', $output);

        $this->responses[] = self::finderResponse([]);

        [$status, $output] = $this->twf('office', 'list', '--json');

        self::assertSame(0, $status);
        self::assertSame('[]' . PHP_EOL, $output);
    }

    public function testOfficeListOfAProfile(): void
    {
        $this->store()->saveProfile('acme', self::profile());

        [$status, , $error] = $this->twf('office', 'list');

        self::assertSame(1, $status);
        self::assertStringContainsString('Not logged in to Twinfield. Run: vendor/bin/twf login', $error);
        self::assertSame([], $this->requests);

        $this->responses[] = self::finderResponse(self::OFFICES);

        [$status, $output] = $this->twf('office', 'list', '--profile=acme');

        self::assertSame(0, $status);
        self::assertStringContainsString('Example B.V.', $output);

        putenv('TWINFIELD_PROFILE=acme');
        $this->responses[] = self::finderResponse(self::OFFICES);

        [$status, $output] = $this->twf('office', 'list');

        self::assertSame(0, $status);
        self::assertStringContainsString('Example B.V.', $output);
    }

    public function testProcessXmlSendsTheRequestOfStandardInput(): void
    {
        $this->store()->saveProfile('default', self::profile());
        $this->responses[] = self::processXmlResponse(
            '<office result="1"><code>1000</code><name>A &amp; B</name></office>',
        );

        [$status, $output, $error] = $this->twfWithInput(
            '<read><type>office</type><code>1000</code></read>',
            'process-xml',
            '--office=1000',
        );

        self::assertSame('', $error);
        self::assertSame(0, $status);
        self::assertSame('<office result="1"><code>1000</code><name>A &amp; B</name></office>' . PHP_EOL, $output);
        self::assertCount(1, $this->requests);
        self::assertStringContainsString('&lt;read&gt;&lt;type&gt;office&lt;/type&gt;', $this->requests[0]);
        self::assertStringContainsString('<CompanyCode>1000</CompanyCode>', $this->requests[0]);
    }

    public function testXmlIsTheShortNameOfProcessXml(): void
    {
        $this->store()->saveProfile('default', self::profile());
        $this->responses[] = self::processXmlResponse('<office result="1"><code>1000</code></office>');

        [$status, $output] = $this->twfWithInput('<read><type>office</type><code>1000</code></read>', 'xml');

        self::assertSame(0, $status);
        self::assertSame('<office result="1"><code>1000</code></office>' . PHP_EOL, $output);
    }

    public function testProcessXmlSendsTheRequestOfAFile(): void
    {
        $this->store()->saveProfile('default', self::profile());
        $this->responses[] = self::processXmlResponse('<office result="1"><code>1000</code></office>');

        $file = $this->directory . '/request.xml';

        file_put_contents($file, '<read><type>office</type><code>1000</code></read>');

        try {
            [$status, $output] = $this->twf('process-xml', $file);
        } finally {
            unlink($file);
        }

        self::assertSame(0, $status);
        self::assertStringContainsString('<code>1000</code>', $output);
        self::assertStringContainsString('&lt;code&gt;1000&lt;/code&gt;', $this->requests[0]);
        self::assertStringNotContainsString('<CompanyCode>', $this->requests[0]);
    }

    public function testProcessXmlPrintsTheResponseOfAFailedRequest(): void
    {
        $this->store()->saveProfile('default', self::profile());
        $this->responses[] = self::processXmlResponse(
            '<office result="0"><code msgtype="error" msg="No access." result="0">9999</code></office>',
        );

        [$status, $output, $error] = $this->twfWithInput(
            '<read><type>office</type><code>9999</code></read>',
            'process-xml',
        );

        self::assertSame(1, $status);
        self::assertStringContainsString('msg="No access."', $output);
        self::assertStringContainsString('Error: office/code: No access.', $error);
    }

    public function testProcessXmlWithoutARequest(): void
    {
        $this->store()->saveProfile('default', self::profile());

        [$status, , $error] = $this->twf('process-xml');

        self::assertSame(1, $status);
        self::assertStringContainsString('No XML request. Give a file or pipe the XML: vendor/bin/twf', $error);

        [$status, , $error] = $this->twf('process-xml', $this->directory . '/no-such-file.xml');

        self::assertSame(1, $status);
        self::assertStringContainsString('Cannot read the file', $error);

        [$status, , $error] = $this->twfWithInput('<read>', 'process-xml');

        self::assertSame(1, $status);
        self::assertStringContainsString('not valid XML', $error);
        self::assertSame([], $this->requests);
    }

    public function testAGroupAloneShowsItsCommands(): void
    {
        foreach ([['office'], ['office', '--help'], ['help', 'office']] as $arguments) {
            [$status, $output] = $this->twf(...$arguments);

            self::assertSame(0, $status);
            self::assertMatchesRegularExpression('/^  office:list +List the offices$/m', $output);
            self::assertDoesNotMatchRegularExpression('/^  login /m', $output);
        }
    }

    public function testHelpOfACommandInAGroup(): void
    {
        foreach ([['office', 'list', '--help'], ['help', 'office', 'list']] as $arguments) {
            [$status, $output] = $this->twf(...$arguments);

            self::assertSame(0, $status);
            self::assertStringContainsString('--limit=LIMIT', $output);
            self::assertStringContainsString('vendor/bin/twf office list --json', $output);
        }
    }

    public function testArgumentsOfOtherCommandsStayAsTheyAre(): void
    {
        $application = new Application($this->store());

        self::assertSame([], $application->arguments([]));
        self::assertSame(['list'], $application->arguments(['list']));
        self::assertSame(['help', 'list'], $application->arguments(['help', 'list']));
        self::assertSame(['status', 'list'], $application->arguments(['status', 'list']));
        self::assertSame(['--version'], $application->arguments(['--version']));
        self::assertSame(['office', 'foo'], $application->arguments(['office', 'foo']));
        self::assertSame(['office:list', '-L', '2'], $application->arguments(['office', 'list', '-L', '2']));
    }

    /**
     * A response of the Finder with these rows.
     *
     * @param list<list<string>> $rows
     */
    private static function finderResponse(array $rows, ?int $totalRows = null): Response
    {
        $strings = static fn (array $values): string => implode('', array_map(
            static fn (string $value): string => '<string>' . htmlspecialchars($value, ENT_XML1) . '</string>',
            $values,
        ));

        $items = implode('', array_map(
            static fn (array $row): string => '<ArrayOfString>' . $strings($row) . '</ArrayOfString>',
            $rows,
        ));

        $columns = $strings(['Code', 'Naam', 'ID', 'Land', 'Btw-periode', 'Startkwartaal']);
        $totalRows ??= count($rows);

        return new Response(200, ['Content-Type' => 'text/xml; charset=utf-8'], <<<XML
            <?xml version="1.0" encoding="utf-8"?>
            <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
              <soap:Body>
                <SearchResponse xmlns="http://www.twinfield.com/">
                  <SearchResult />
                  <data>
                    <TotalRows>{$totalRows}</TotalRows>
                    <Columns>{$columns}</Columns>
                    <Items>{$items}</Items>
                  </data>
                </SearchResponse>
              </soap:Body>
            </soap:Envelope>
            XML);
    }

    /**
     * A response of ProcessXml with this XML as result.
     */
    private static function processXmlResponse(string $xml): Response
    {
        $result = htmlspecialchars($xml, ENT_XML1);

        return new Response(200, ['Content-Type' => 'text/xml; charset=utf-8'], <<<XML
            <?xml version="1.0" encoding="utf-8"?>
            <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
              <soap:Body>
                <ProcessXmlStringResponse xmlns="http://www.twinfield.com/">
                  <ProcessXmlStringResult>{$result}</ProcessXmlStringResult>
                </ProcessXmlStringResponse>
              </soap:Body>
            </soap:Envelope>
            XML);
    }

    /**
     * An HTTP client that gives the responses of the test instead of calling Twinfield.
     */
    private function httpClient(): Client
    {
        $stack = HandlerStack::create(new MockHandler($this->responses));

        $stack->push(Middleware::tap(function (RequestInterface $request): void {
            $this->requests[] = (string) $request->getBody();
        }));

        $this->responses = [];

        return new Client(['handler' => $stack]);
    }

    /**
     * @return array{int, string, string}
     */
    private function twf(string ...$arguments): array
    {
        return $this->twfWithInput('', ...$arguments);
    }

    /**
     * @return array{int, string, string} Exit status, standard output and standard error.
     */
    private function twfWithInput(string $input, string ...$arguments): array
    {
        return $this->twfAs('vendor/bin/twf', ...$arguments, input: $input);
    }

    /**
     * @return array{int, string, string} Exit status, standard output and standard error.
     */
    private function twfAs(string $script, string ...$arguments): array
    {
        $input = $arguments['input'] ?? '';

        unset($arguments['input']);

        // bin/twf does this with the script name it was called with.
        CredentialsException::calledAs($script);

        $application = new Application($this->store(), $this->httpClient());
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->setInputs([$input]);

        // bin/twf reads `office list` as `office:list` in the same way.
        $arguments = $application->arguments(array_values($arguments));

        // Without `decorated` the output takes the colours of the terminal the tests run in.
        $status = $tester->run(self::input($application, ...$arguments), [
            'capture_stderr_separately' => true,
            'decorated' => false,
        ]);

        return [$status, $tester->getDisplay(), $tester->getErrorOutput()];
    }

    /**
     * The command line as the array an ApplicationTester takes: `--profile=acme` becomes
     * `['--profile' => 'acme']`, and an argument after the command gets the name the command gives it.
     *
     * @return array<string, string|true>
     */
    private static function input(Application $application, string ...$arguments): array
    {
        $input = [];
        $names = [];

        foreach ($arguments as $argument) {
            if (str_starts_with($argument, '--')) {
                $parts = explode('=', $argument, 2);

                $input[$parts[0]] = $parts[1] ?? true;

                continue;
            }

            if (!isset($input['command'])) {
                $input['command'] = $argument;

                $names = $application->has($argument) ? array_map(
                    static fn (InputArgument $argument): string => $argument->getName(),
                    array_values($application->get($argument)->getDefinition()->getArguments()),
                ) : [];

                continue;
            }

            $input[array_shift($names) ?? $argument] = $argument;
        }

        return $input;
    }
}
