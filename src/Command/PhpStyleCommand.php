<?php

declare(strict_types=1);

namespace PlotBox\Standards\Command;

use PlotBox\PhpcsParse\CodeIssue as PhpcsCodeIssue;
use PlotBox\PhpGitOps\Git\BranchModifications;
use PlotBox\PhpGitOps\Git\BranchModificationsFactory;
use PlotBox\PhpGitOps\Git\Git;
use PlotBox\PhpGitOps\RelativeFile;
use PlotBox\Standards\Util\Shell;
use PlotBox\Standards\Util\Util;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

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

    private Git $git;
    private string $cwd;

    /** @param list<string> $excludedDirectories */
    public function __construct(
        private ?string $realAppRoot = null,
        private array $excludedDirectories = [],
        private ?string $phpVersion = null
    ) {
        parent::__construct(self::COMMAND_NAME);
        $this->cwd = getcwd();
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

        $tempFileList = $this->createTempFileList($filesToCheck);

        try {
            $sarbBaselinePath = $input->getOption('sarb-baseline') ?: $this->cwd . '/phpcs.baseline';
            $ignoreBaseline = (bool) $input->getOption('ignore-baseline');
            $shellCommand = $this->getShellCommand($tempFileList, $sarbBaselinePath, $ignoreBaseline);

            $result = Shell::exec($shellCommand);

            if (!$result->stdOut) {
                $io->error("Error: No output received from phpcs. Command:\n" . $shellCommand);
                return Command::INVALID;
            }

            $issues = $this->getProcessedIssues($result->stdOut, $ignoreBaseline);

            if (count($issues) === 0) {
                $io->success('Style check passed!');
                $io->writeln(Util::thumbsUpAscii());
                return self::SUCCESS;
            }

            $this->renderIssuesTable($issues, $output);

            return self::FAILURE;
        } finally {
            @unlink($tempFileList);
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
        $modifiedFiles = $this->getModifiedPhpFiles($branchModifications);

        if (count($modifiedFiles) === 0) {
            return [];
        }

        echo "Checking style in changes since git ancestor: {$branchModifications->getParent()->getName()}\n\n";
        echo "Checking style for files:\n" . $this->getFileListString($modifiedFiles, $branchModifications) . "\n\n";

        return $modifiedFiles;
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
    private function getProcessedIssues(string $resultJson, bool $ignoreBaseline): array
    {
        $sarbIssues = $this->getSarbIssues($resultJson, $ignoreBaseline);
        $sarbIssues = $this->makePathsRelative($sarbIssues);
        $sarbIssues = $this->filterExcludedPaths($sarbIssues);

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

    private function getShellCommand(
        string $tempFileListPath,
        string $sarbBaselinePath,
        bool $ignoreBaseline = false
    ): string {
        if ($this->phpVersion) {
            $phpVersion = $this->phpVersion;
        } else {
            $phpVersion = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        }

        $pathsToScan = '--file-list=' . escapeshellarg($tempFileListPath);

        $errorMode = E_ERROR | E_PARSE;
        $lenientPhpRuntime = "php -d memory_limit=-1 -d error_reporting=$errorMode";
        $phpcsCommand = "XDEBUG_MODE=off $lenientPhpRuntime vendor/bin/phpcs \
            --runtime-set testVersion $phpVersion \
            --extensions=php \
            --parallel=" . self::NUM_THREADS . " \
            --standard=Plotbox \
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
        $this->git->fetchAll();
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
