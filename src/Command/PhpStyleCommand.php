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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Optimised code style checker for PlotBox repositories. Will only check modified files (using git).
 * Will return results in the console with hyperlinks to files (using the real host path if provided).
 */
final class PhpStyleCommand extends Command
{
    private const int MAX_FILES_CHANGED_BEFORE_IGNORE_CODE_STYLE = 400;
    private const int NUM_THREADS = 6;

    private const array WHITELISTED_DIRECTORIES = [
        'tests',
        'classes',
        'libraries/core/modules',
        'src',
        'app'
    ];

    private const string COMMAND_NAME = 'php-style';

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

    /** @inheritdoc */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        ini_set('memory_limit', '-1');
        chdir($this->cwd);

        $io = new SymfonyStyle($input, $output);

        $branchModifications = $this->getBranchModifications();
        $allTouched = $this->getModifiedPhpFiles($branchModifications);
        if (count($allTouched) > self::MAX_FILES_CHANGED_BEFORE_IGNORE_CODE_STYLE) {
            $io->info('Checking style for directories: ' . implode(', ', self::whitelistedDirsThatExist()) . "\n\n");
            $allTouched = null;
        } elseif (count($allTouched) === 0) {
            $io->success('Style check passed - No relevant PHP files modified from parent');
            return self::SUCCESS;
        } else {
            echo "Checking style in changes since git ancestor: {$branchModifications->getParent()->getName()}\n\n";
            $fileListString = $this->getFileListString($allTouched, $branchModifications);
            echo "Checking style for files:\n" . $fileListString . "\n\n";
        }

        $sarbBaselinePath = $input->getOption('sarb-baseline') ?: $this->cwd . '/phpcs.baseline';
        $ignoreBaseline = (bool) $input->getOption('ignore-baseline');
        $shellCommand = $this->getShellCommand($allTouched, $sarbBaselinePath, $ignoreBaseline);
        $result = Shell::exec($shellCommand);
        $resultJson = $result->stdOut;
        if (!$resultJson) {
            $io->error("Error: No output received from phpcs. Command:\n" . $shellCommand);
            return Command::INVALID;
        }

        $issues = $this->getSarbIssues($resultJson, $ignoreBaseline);
        $issues = $this->makePathsRelative($issues);
        $issues = $this->filterExcludedPaths($issues);

        $phpcsIssues = [];
        foreach ($issues as $sarbIssue) {
            $phpcsIssues[] = new PhpcsCodeIssue(
                $sarbIssue->file,
                $sarbIssue->originalToolDetails->line,
                $sarbIssue->originalToolDetails->column,
                $sarbIssue->originalToolDetails->source,
                $sarbIssue->originalToolDetails->message,
                $sarbIssue->originalToolDetails->type,
                $sarbIssue->originalToolDetails->severity,
                $sarbIssue->originalToolDetails->fixable
            );
        }

        if (count($phpcsIssues) === 0) {
            $io->success('Style check passed!');
            $io->writeln(Util::thumbsUpAscii());
            return self::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['File', '(Shortened) Type', 'Message']);
        foreach ($phpcsIssues as $phpcsIssue) {
            $type = $this->tryMakeClickableLink(
                $phpcsIssue->getSource()
            );

            $fileLink = Util::makeConsoleLinkFromPath(
                $phpcsIssue->getFile(),
                $phpcsIssue->getLine(),
                $this->realAppRoot,
                100
            );

            $table->addRow([
                $fileLink,
                $type,
                Util::elipsesString($phpcsIssue->getMessage(), maxChars: 50)
            ]);
        }
        $table->render();

        return count($issues) > 0 ? self::FAILURE : self::SUCCESS;
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

    /** @param list<string>|null $allTouched */
    private function getShellCommand(
        ?array $allTouched,
        string $sarbBaselinePath,
        bool $ignoreBaseline = false
    ): string {
        if ($this->phpVersion) {
            $phpVersion = $this->phpVersion;
        } else {
            $phpVersion = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        }

        $pathsToScan = $allTouched ?? self::whitelistedDirsThatExist();

        foreach ($pathsToScan as $key => $path) {
            $pathsToScan[$key] = escapeshellarg($path);
        }
        $allPaths = implode(' ', $pathsToScan);

        $errorMode = E_ERROR | E_PARSE;
        $lenientPhpRuntime = "php -d memory_limit=-1 -d error_reporting=$errorMode";
        $phpcsCommand = "XDEBUG_MODE=off $lenientPhpRuntime vendor/bin/phpcs \
            --runtime-set testVersion $phpVersion \
            --extensions=php \
            --parallel=" . self::NUM_THREADS . " \
            --standard=Plotbox \
            --report=json \
            $allPaths | uniq";

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
    // phpcs:ignore CognitiveComplexity.Complexity.MaximumComplexity.TooHigh
    private function getModifiedPhpFiles(
        BranchModifications $branchModifications
    ): array {
        $allTouched = $branchModifications->getModifiedFilePaths();
        foreach ($allTouched as $i => $touchedFile) {
            if (!file_exists($this->cwd . '/' . $touchedFile)) {
                unset($allTouched[$i]);
            }
        }

        foreach ($allTouched as $i => $touchedFile) {
            if (!str_ends_with($touchedFile, '.php')) {
                unset($allTouched[$i]);
            }
        }

        foreach ($allTouched as $i => $touchedFile) {
            if (!$this->isPathInWhitelistedDirectories($touchedFile)) {
                unset($allTouched[$i]);
            }
        }

        foreach ($allTouched as $i => $touchedFile) {
            if ($this->isExcluded($touchedFile)) {
                unset($allTouched[$i]);
            }
        }

        return array_values($allTouched);
    }

    /**
     * @param list<SarbCodeIssue> $issues
     * @return list<SarbCodeIssue>
     */
    private function makePathsRelative(array $issues): array
    {
        $appRoot = Util::getProjectRoot();
        foreach ($issues as $issue) {
            $issue->file = $this->replaceFromStart('/opt/project/', $issue->file);
            $issue->file = $this->replaceFromStart('/app/', $issue->file);
            $issue->file = $this->replaceFromStart($appRoot . '/', $issue->file);
        }

        return $issues;
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

    private function replaceFromStart(
        string $needle,
        string $haystack
    ): string {
        if (str_starts_with($haystack, $needle)) {
            return substr($haystack, strlen($needle));
        }

        return $haystack;
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
        $isWhitelisted = false;
        foreach (self::whitelistedDirsThatExist() as $whitelistedDirectory) {
            if (str_starts_with($touchedFile, $whitelistedDirectory)) {
                $isWhitelisted = true;
                break;
            }
        }
        return $isWhitelisted;
    }

    /** @param list<string> $allTouched */
    private function getFileListString(
        array $allTouched,
        BranchModifications $branchModifications
    ): string {
        $fileList = [];
        foreach ($allTouched as $file) {
            $newPart = '';
            if ($branchModifications->isNewFile(new RelativeFile($file))) {
                $newPart = ' (NEW)';
            }
            $fileList[] = " - {$newPart} {$file}";
        }
        return implode("\n", $fileList);
    }

    private function tryMakeClickableLink(
        string $type
    ): string {
        $parts = explode('.', $type);
        $uiType = "{$parts[2]}.{$parts[3]}";
        if (str_starts_with($type, 'Slevomat')) {
            $link = 'https://github.com/slevomat/coding-standard?tab=readme-ov-file#:~:text=';
            $link .= implode('.', array_slice($parts, 0, 3));
            return Util::makeConsoleLink($link, $uiType);
        }

        $codeSnifferOwnedRulesets = [
            'Squiz',
            'PSR1',
            'Generic',
            'PEAR',
            'MySource',
            'PSR2',
            'PSR12',
            'Zend'
        ];
        if (in_array($parts[0], $codeSnifferOwnedRulesets)) {
            $link = 'https://github.com/squizlabs/PHP_CodeSniffer/blob/master/src/Standards';
            $link .= "/{$parts[0]}/Sniffs/{$parts[1]}/{$parts[2]}Sniff.php";
            return Util::makeConsoleLink($link, $uiType);
        }

        if (str_starts_with($type, 'MediaWiki')) {
            $link = 'https://github.com/wikimedia/mediawiki-tools-codesniffer/blob/master/MediaWiki/Sniffs/';
            $link .= "{$parts[1]}/{$parts[2]}Sniff.php";
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

    private function isExcluded(
        string $touchedFile
    ): bool {
        $isExcluded = false;
        foreach ($this->excludedDirectories as $excludedDirectory) {
            if (str_starts_with($touchedFile, $excludedDirectory)) {
                $isExcluded = true;
                break;
            }
        }
        return $isExcluded;
    }
}
