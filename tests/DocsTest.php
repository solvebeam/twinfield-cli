<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Cli\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use SolveBeam\Twinfield\Cli\Application;
use SolveBeam\Twinfield\Cli\Command\Command;
use SolveBeam\Twinfield\Cli\CredentialStore;

/**
 * The manual in `docs/` is written by hand: each command has a page, and the page names its
 * arguments and options.
 */
final class DocsTest extends TestCase
{
    private const string MANUAL = __DIR__ . '/../docs/src/content/docs/manual';

    #[DataProvider('commands')]
    public function testCommandHasAPageInTheManual(string $name): void
    {
        // `office:list` is the page `twf_office_list.md`, like `gh_repo_list` in the manual of the GitHub CLI.
        $file = self::MANUAL . '/twf_' . str_replace(':', '_', $name) . '.md';

        self::assertFileExists($file, sprintf('Write a page for `twf %s`.', str_replace(':', ' ', $name)));

        $page = (string) file_get_contents($file);

        self::assertStringContainsString('title: twf ' . str_replace(':', ' ', $name) . "\n", $page);

        $application = new Application($this->store());

        $definition = $application->get($name)->getDefinition();

        foreach ($definition->getArguments() as $argument) {
            self::assertStringContainsString('`<' . $argument->getName() . '>`', $page);
        }

        foreach ($application->get($name)->getAliases() as $alias) {
            self::assertStringContainsString('`twf ' . $alias . '`', $page);
        }

        foreach ($definition->getOptions() as $option) {
            self::assertMatchesRegularExpression(
                '/`--' . preg_quote($option->getName(), '/') . '[=`]/',
                $page,
                sprintf('Describe --%s on the page of `twf %s`.', $option->getName(), str_replace(':', ' ', $name)),
            );
        }
    }

    public function testGroupHasAPageInTheManual(): void
    {
        foreach ((new Application($this->store()))->getNamespaces() as $group) {
            // `_complete` of Symfony is not a group of `twf`.
            if (str_starts_with($group, '_')) {
                continue;
            }

            self::assertFileExists(self::MANUAL . '/twf_' . $group . '.md');
        }
    }

    /**
     * The commands of `twf` itself, without `help`, `list` and `completion` of Symfony.
     *
     * @return iterable<string, array{string}>
     */
    public static function commands(): iterable
    {
        // A data provider runs before setUp(): the store is not used, only the commands are.
        $application = new Application(new CredentialStore(sys_get_temp_dir() . '/twinfield-cli/credentials.json'));

        foreach ($application->all() as $name => $command) {
            // An alias, like `xml` of `process-xml`, is on the page of its command.
            if ($command instanceof Command && $name === $command->getName()) {
                yield $name => [$name];
            }
        }
    }
}
