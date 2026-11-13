<?php

declare(strict_types=1);

namespace PlotBox\Standards\Command;

use Closure;
use PlotBox\PhpcsParse\CodeIssue as PhpcsCodeIssue;
use PlotBox\PhpGitOps\Git\BranchModifications;
use PlotBox\PhpGitOps\Git\BranchModificationsFactory;
use PlotBox\PhpGitOps\Git\Git;
use PlotBox\PhpGitOps\RelativeFile;
use PlotBox\Standards\Baseline\BaselineIssue;
use PlotBox\Standards\Baseline\LooseBaseline;
use PlotBox\Standards\Baseline\PhpcsBaselineIssues;
use PlotBox\Standards\Util\Shell;
use PlotBox\Standards\Util\Util;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * Optimised code style checker for PlotBox repositories. Will only check modified files (using git).
 * Will return results in the console with hyperlinks to files (using the real host path if provided).
 */
final class PhpStyleCommand extends Command
{
    private const NUM_THREADS = 6;

    private const WHITELISTED_DIRECTORIES = [
        'tests',
        'classes',
        'libraries/core/modules',
        'src',
        'app'
    ];

    private const COMMAND_NAME = 'php-style';

    public const LOOSE_BASELINE = 'phpcs-baseline.toml';

    public const SARB_BASELINE = 'phpcs.baseline';

    private Git $git;
    private string $cwd;
    private ?BranchModifications $branchModifications = null;
    private int $changedLineIssueCount = 0;

    /** @param list<string> $excludedDirectories */
    public function __construct(
        private ?string $realAppRoot = null,
        private array $excludedDirectories = [],
        private ?string $phpVersion = null,
        private ?string $phpcsConfigPath = null,
        private bool $strictChangedLines = false
    ) {
        parent::__construct(self::COMMAND_NAME);
        $envRoot = getenv('PB_PROJECT_ROOT');
        $this->cwd = is_string($envRoot) && $envRoot !== '' ? $envRoot : getcwd();
        $this->git = new Git($this->cwd);
    }

    /** @inheritdoc */
    protected function configure(): void
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->addArgument(
                name: 'path',
                mode: InputArgument::IS_ARRAY,
                description: 'Force scanning on certain directories/files'
            )
            ->addOption(
                name: 'sarb-baseline',
                description: 'Path to sarb baseline file'
            )
            ->addOption(
                name: 'baseline',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Baseline file. A .toml path is a loose (file, code, message, count) baseline; '
                    . 'anything else is SARB. Defaults to ' . self::LOOSE_BASELINE . ' when it exists, else '
                    . self::SARB_BASELINE
            )
            ->addOption(
                name: 'ignore-baseline',
                description: 'Run php style checks without filtering by the baseline'
            )
            ->setDescription('Check PHP code style');
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        ini_set('memory_limit', '-1');
        chdir($this->cwd);

        $io = new SymfonyStyle($input, $output);

        $filesToCheck = $this->resolveFilesToCheck($input, $io);
        if ($filesToCheck === []) {
            $io->success('Style check passed - No relevant PHP files modified from parent');
            return self::SUCCESS;
        }

        $syntaxErrors = $this->findSyntaxErrors($filesToCheck);
        if ($syntaxErrors !== []) {
            $io->error('Style check aborted - the following file(s) are not valid PHP and must be fixed first:');
            foreach ($syntaxErrors as $file => $message) {
                $io->writeln("<error> {$file} </error>");
                $io->writeln($message);
                $io->newLine();
            }
            return self::FAILURE;
        }

        $tempFileList = $this->createTempFileList($filesToCheck);
        $phpVersion = $this->resolvePhpVersion();
        $tempRuleset = $this->maybeWriteVersionAdaptedRuleset($phpVersion);

        try {
            $baselinePath = $this->resolveBaselinePath($input);
            $looseBaseline = str_ends_with($baselinePath, '.toml');
            $ignoreBaseline = (bool) $input->getOption('ignore-baseline');
            $rawPhpcsJson = $ignoreBaseline || $looseBaseline;
            $shellCommand = $this->getShellCommand($tempFileList, $baselinePath, $rawPhpcsJson, $phpVersion, $tempRuleset);

            $result = Shell::exec($shellCommand);

            if (!$result->stdOut) {
                $io->error("Error: No output received from phpcs. Command:\n" . $shellCommand);
                return Command::INVALID;
            }

            $issues = $this->getProcessedIssues(
                $result->stdOut,
                $rawPhpcsJson,
                $looseBaseline && !$ignoreBaseline ? $baselinePath : null,
                $io
            );

            if (count($issues) === 0) {
                $io->success('Style check passed!');
                $io->writeln(Util::thumbsUpAscii());
                return self::SUCCESS;
            }

            $this->renderIssuesTable($issues, $output);
            $this->warnAboutChangedLineIssues($io);

            return self::FAILURE;
        } finally {
            @unlink($tempFileList);
            if ($tempRuleset !== null) {
                @unlink($tempRuleset);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function resolveFilesToCheck(InputInterface $input, SymfonyStyle $io): array
    {
        $paths = $input->getArgument('path');
        if (count($paths) > 0) {
            $io->info('Checking style for specified paths: ' . implode(', ', $paths) . "\n\n");
            return $paths;
        }

        $branchModifications = $this->getBranchModifications();
        $this->branchModifications = $branchModifications;
        $modifiedFiles = $this->getModifiedPhpFiles($branchModifications);

        if (count($modifiedFiles) === 0) {
            return [];
        }

        echo "Checking style in changes since git ancestor: {$branchModifications->getParent()->getName()}\n\n";
        echo "Checking style for files:\n" . $this->getFileListString($modifiedFiles, $branchModifications) . "\n\n";

        return $modifiedFiles;
    }

    /**
     * Pre-flight guard: reject any file that is not valid PHP before running phpcs.
     *
     * phpcs tokenises invalid PHP rather than rejecting it, and certain sniffs walk
     * the brace/scope map of the resulting (corrupt) token stream. A malformed
     * structure can send such a sniff into an infinite loop, hanging the whole
     * php-style run indefinitely. Failing fast on a clear `php -l` error is far
     * better than an unbounded hang.
     *
     * @param list<string> $files
     * @return array<string, string> map of file => `php -l` error output (empty when all valid)
     */
    private function findSyntaxErrors(array $files): array
    {
        $errors = [];
        foreach ($files as $file) {
            if (!str_ends_with($file, '.php') || !is_file($file)) {
                continue;
            }

            $result = Shell::exec('php -l ' . escapeshellarg($file) . ' 2>&1');
            if ($result->exitCode !== 0) {
                $errors[$file] = trim((string) $result->stdOut);
            }
        }

        return $errors;
    }

    /**
     * @param list<string> $files
     */
    private function createTempFileList(array $files): string
    {
        $tempFileList = tempnam(sys_get_temp_dir(), 'phpcs_file_list_');
        file_put_contents($tempFileList, implode("\n", $files));

        return $tempFileList;
    }

    /**
     * @return list<PhpcsCodeIssue>
     */
    private function getProcessedIssues(
        string $resultJson,
        bool $rawPhpcsJson,
        ?string $looseBaselinePath,
        SymfonyStyle $io
    ): array {
        $sarbIssues = $this->getSarbIssues($resultJson, $rawPhpcsJson);
        $sarbIssues = $this->makePathsRelative($sarbIssues);
        $sarbIssues = $this->filterExcludedPaths($sarbIssues);
        if ($looseBaselinePath !== null) {
            $sarbIssues = $this->removeLooseBaselineIssues(
                $sarbIssues,
                LooseBaseline::fromFile($looseBaselinePath),
                $this->changedLineFilter($io)
            );
        }

        return array_map(static function (SarbCodeIssue $sarbIssue): PhpcsCodeIssue {
            return new PhpcsCodeIssue(
                $sarbIssue->file,
                $sarbIssue->originalToolDetails->line,
                $sarbIssue->originalToolDetails->column,
                $sarbIssue->originalToolDetails->source,
                $sarbIssue->originalToolDetails->message,
                $sarbIssue->originalToolDetails->type,
                $sarbIssue->originalToolDetails->severity,
                $sarbIssue->originalToolDetails->fixable
            );
        }, $sarbIssues);
    }

    /**
     * Baselined issues on lines the branch changed are kept, so touched code must comply.
     *
     * @param list<SarbCodeIssue> $issues
     * @param (Closure(string, int): bool)|null $wasChanged
     * @return list<SarbCodeIssue>
     */
    private function removeLooseBaselineIssues(
        array $issues,
        LooseBaseline $baseline,
        ?Closure $wasChanged
    ): array {
        $baselineIssues = [];
        foreach ($issues as $issue) {
            $baselineIssues[] = new BaselineIssue($issue->file, PhpcsBaselineIssues::code($issue), $issue->message);
        }
        $unmatched = [];
        foreach ($baseline->unmatched($baselineIssues) as $baselineIssue) {
            $unmatched[spl_object_id($baselineIssue)] = true;
        }

        $remaining = [];
        foreach ($baselineIssues as $index => $baselineIssue) {
            $issue = $issues[$index];
            if (isset($unmatched[spl_object_id($baselineIssue)])) {
                $remaining[] = $issue;
            } elseif ($wasChanged !== null && $wasChanged($issue->file, $issue->line)) {
                $remaining[] = $issue;
                $this->changedLineIssueCount++;
            }
        }

        return $remaining;
    }

    /**
     * Mainline branches are skipped: a release branch diffs against master, so every line it
     * carries would count as changed.
     *
     * @return (Closure(string, int): bool)|null
     */
    private function changedLineFilter(SymfonyStyle $io): ?Closure
    {
        if (!$this->strictChangedLines) {
            return null;
        }
        try {
            $branch = $this->git->getCurrentBranch()->getName();
            if (self::isMainlineBranch($branch)) {
                $io->note('Changed-line check skipped: mainline branch ' . $branch);
                return null;
            }
            $modifications = $this->branchModifications ?? $this->getBranchModifications();
        } catch (Throwable $exception) {
            $io->note('Changed-line check skipped: ' . $exception->getMessage());
            return null;
        }

        return static fn (string $file, int $line): bool => $modifications->wasModified(
            new RelativeFile($file),
            $line
        );
    }

    private static function isMainlineBranch(string $branch): bool
    {
        $name = str_starts_with($branch, 'origin/')
            ? substr($branch, strlen('origin/'))
            : $branch;

        return in_array($name, ['master', 'main', 'develop'], true) || str_starts_with($name, 'release/');
    }

    private function warnAboutChangedLineIssues(SymfonyStyle $io): void
    {
        if ($this->changedLineIssueCount === 0) {
            return;
        }
        $io->warning(sprintf(
            '%d of these are baselined but on lines this branch changed. Fix them; code a branch touches '
            . 'must comply (ADR 0020).',
            $this->changedLineIssueCount
        ));
    }

    private function resolveBaselinePath(InputInterface $input): string
    {
        $explicit = $input->getOption('baseline');
        if (is_string($explicit) && $explicit !== '') {
            return str_starts_with($explicit, '/') ? $explicit : $this->cwd . '/' . $explicit;
        }
        $sarb = $input->getOption('sarb-baseline');
        if (is_string($sarb) && $sarb !== '') {
            return $sarb;
        }
        $loose = $this->cwd . '/' . self::LOOSE_BASELINE;

        return is_file($loose) ? $loose : $this->cwd . '/' . self::SARB_BASELINE;
    }

    /**
     * @param list<PhpcsCodeIssue> $issues
     */
    private function renderIssuesTable(array $issues, OutputInterface $output): void
    {
        $table = new Table($output);
        $table->setHeaders(['File', '(Shortened) Type', 'Message']);

        foreach ($issues as $issue) {
            $table->addRow([
                Util::makeConsoleLinkFromPath(
                    $issue->getFile(),
                    $issue->getLine(),
                    $this->realAppRoot,
                    100
                ),
                $this->tryMakeClickableLink($issue->getSource()),
                Util::elipsesString($issue->getMessage(), maxChars: 50)
            ]);
        }

        $table->render();
    }

    /** @return list<string> */
    private static function whitelistedDirsThatExist(): array
    {
        $existingDirs = [];
        foreach (self::WHITELISTED_DIRECTORIES as $dir) {
            if (is_dir($dir)) {
                $existingDirs[] = $dir;
            }
        }
        return $existingDirs;
    }

    private function resolvePhpVersion(): string
    {
        return $this->phpVersion ?? (PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION);
    }

    /**
     * When the target PHP version is below 8.3, typed class constants are not valid syntax.
     * The PlotBox ruleset enables ClassConstantTypeHint.MissingNativeTypeHint unconditionally,
     * but Slevomat does not respect phpcs's testVersion runtime setting for this sniff.
     * We generate a temporary wrapper ruleset that disables the native type hint enforcement
     * so that the style check correctly reflects what is valid for the target PHP version.
     */
    private function maybeWriteVersionAdaptedRuleset(string $phpVersion): ?string
    {
        if (version_compare($phpVersion, '8.3', '>=')) {
            return null;
        }

        $baseStandard = $this->phpcsConfigPath ?? 'PlotBox';

        $xml = <<<XML
            <?xml version="1.0"?>
            <ruleset name="PlotBoxVersionAdapted">
                <rule ref="{$baseStandard}"/>
                <rule ref="SlevomatCodingStandard.TypeHints.ClassConstantTypeHint">
                    <properties>
                        <property name="enableNativeTypeHint" value="false"/>
                    </properties>
                </rule>
            </ruleset>
            XML;

        $tempPath = tempnam(sys_get_temp_dir(), 'phpcs_ruleset_') . '.xml';
        file_put_contents($tempPath, $xml);

        return $tempPath;
    }

    private function getShellCommand(
        string $tempFileListPath,
        string $sarbBaselinePath,
        bool $ignoreBaseline,
        string $phpVersion,
        ?string $tempRuleset
    ): string {
        $pathsToScan = '--file-list=' . escapeshellarg($tempFileListPath);

        $standard = $tempRuleset
            ? escapeshellarg($tempRuleset)
            : ($this->phpcsConfigPath ? escapeshellarg($this->phpcsConfigPath) : 'PlotBox');

        $errorMode = E_ERROR | E_PARSE;
        $lenientPhpRuntime = "php -d memory_limit=-1 -d error_reporting=$errorMode";
        $phpcsCommand = "XDEBUG_MODE=off $lenientPhpRuntime vendor/bin/phpcs \
            --runtime-set testVersion $phpVersion \
            --extensions=php \
            --parallel=" . self::NUM_THREADS . " \
            --standard=$standard \
            --report=json \
            $pathsToScan | uniq";

        if ($ignoreBaseline) {
            return $phpcsCommand;
        }

        return "$phpcsCommand \
        | $lenientPhpRuntime \
            ./vendor/bin/sarb \
            --output-format=json \
            remove $sarbBaselinePath";
    }

    private function getBranchModifications(): BranchModifications
    {
        $this->git->fetchStandardAncestors();
        $branchModificationFactory = new BranchModificationsFactory($this->git);
        return $branchModificationFactory->getBranchModifications();
    }

    /** @return list<string> */
    private function getModifiedPhpFiles(
        BranchModifications $branchModifications
    ): array {
        return array_values(array_filter(
            $branchModifications->getModifiedFilePaths(),
            function (string $file): bool {
                $fullPath = $this->cwd . '/' . $file;

                return file_exists($fullPath)
                    && str_ends_with($file, '.php')
                    && $this->isPathInWhitelistedDirectories($file)
                    && !$this->isExcluded($file);
            }
        ));
    }

    /**
     * @param list<SarbCodeIssue> $issues
     * @return list<SarbCodeIssue>
     */
    private function makePathsRelative(array $issues): array
    {
        $appRoot = Util::getProjectRoot();
        foreach ($issues as $issue) {
            $issue->file = $this->stripPrefixes($issue->file, [
                '/opt/project/',
                '/app/',
                $appRoot . '/',
                $this->cwd . '/',
            ]);
        }

        return $issues;
    }

    private function stripPrefixes(string $haystack, array $prefixes): string
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($haystack, $prefix)) {
                return substr($haystack, strlen($prefix));
            }
        }

        return $haystack;
    }

    /**
     * @param list<SarbCodeIssue> $issues
     * @return list<SarbCodeIssue>
     */
    private function filterExcludedPaths(array $issues): array
    {
        foreach ($issues as $i => $issue) {
            if ($this->isExcluded($issue->file)) {
                unset($issues[$i]);
            }
        }

        return array_values($issues);
    }


    /** @return list<SarbCodeIssue> */
    private function getSarbIssues(string $resultJson, bool $ignoreBaseline): array
    {
        $issues = json_decode($resultJson);
        if ($ignoreBaseline) {
            $phpcsIssues = [];
            foreach ($issues->files as $file => $details) {
                foreach ($details->messages as $message) {
                    $phpcsIssues[] = SarbCodeIssue::fromPhpcs($file, $message);
                }
            }
            return $phpcsIssues;
        }

        foreach ($issues as $i => $issue) {
            $issues[$i] = SarbCodeIssue::fromObject($issue);
        }
        return array_values($issues);
    }

    private function isPathInWhitelistedDirectories(string $touchedFile): bool
    {
        foreach (self::whitelistedDirsThatExist() as $whitelistedDirectory) {
            if (str_starts_with($touchedFile, $whitelistedDirectory)) {
                return true;
            }
        }
        return false;
    }

    /** @param list<string> $allTouched */
    private function getFileListString(
        array $allTouched,
        BranchModifications $branchModifications
    ): string {
        $filesToDisplay = array_slice($allTouched, 0, 10);
        $fileList = [];
        foreach ($filesToDisplay as $file) {
            $newPart = '';
            if ($branchModifications->isNewFile(new RelativeFile($file))) {
                $newPart = ' (NEW)';
            }
            $fileList[] = " - {$newPart} {$file}";
        }

        $output = implode("\n", $fileList);

        if (count($allTouched) > 10) {
            $remainingCount = count($allTouched) - 10;
            $output .= "\n(And $remainingCount more files...)";
        }

        return $output;
    }

    private function tryMakeClickableLink(string $type): string
    {
        $parts = explode('.', $type);
        if (count($parts) < 4) {
            return $type;
        }

        $uiType = "{$parts[2]}.{$parts[3]}";

        if (str_starts_with($type, 'Slevomat')) {
            $link = 'https://github.com/slevomat/coding-standard?tab=readme-ov-file#:~:text=' . implode('.', array_slice($parts, 0, 3));
            return Util::makeConsoleLink($link, $uiType);
        }

        $codeSnifferOwnedRulesets = ['Squiz', 'PSR1', 'Generic', 'PEAR', 'MySource', 'PSR2', 'PSR12', 'Zend'];
        if (in_array($parts[0], $codeSnifferOwnedRulesets)) {
            $link = "https://github.com/squizlabs/PHP_CodeSniffer/blob/master/src/Standards/{$parts[0]}/Sniffs/{$parts[1]}/{$parts[2]}Sniff.php";
            return Util::makeConsoleLink($link, $uiType);
        }

        if (str_starts_with($type, 'MediaWiki')) {
            $link = "https://github.com/wikimedia/mediawiki-tools-codesniffer/blob/master/MediaWiki/Sniffs/{$parts[1]}/{$parts[2]}Sniff.php";
            return Util::makeConsoleLink($link, $uiType);
        }

        if (str_starts_with($type, 'PlotBox')) {
            $relativePath = "vendor/plotbox-io/standards/src/PlotBox/Sniffs/{$parts[1]}/{$parts[2]}Sniff.php";
            if (!$this->realAppRoot) {
                return $relativePath;
            }
            $link = 'file://' . $this->realAppRoot . '/' . $relativePath;
            return Util::makeConsoleLink($link, $uiType);
        }

        return $uiType;
    }

    private function isExcluded(string $touchedFile): bool
    {
        foreach ($this->excludedDirectories as $excludedDirectory) {
            if (str_starts_with($touchedFile, $excludedDirectory)) {
                return true;
            }
        }
        return false;
    }
}
