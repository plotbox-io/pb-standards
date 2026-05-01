<?php

declare(strict_types=1);

namespace PlotBox\Standards\Command;

use PlotBox\Standards\Util\Util;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\mkdir;

/**
 * Compiles AI guidance files for Cursor.
 *
 * Two output modes:
 *
 * 1. Legacy mode (default for back-compat): writes a single combined
 *    `.cursor/rules/all.mdc` containing every guidance source concatenated.
 *
 * 2. Split mode (`$splitOutputs = true`): writes one Cursor rule file per
 *    target so the IDE only loads what is relevant to the file being edited:
 *      - `.cursor/rules/repo-overview.mdc`   (always loaded)
 *      - `.cursor/rules/code-principles.mdc` (auto-attaches to any code file)
 *      - `.cursor/rules/php.mdc`             (auto-attaches to **\/*.php)
 *      - `.cursor/rules/php-tests.mdc`       (auto-attaches to tests/**\/*.php)
 *      - `.cursor/rules/javascript.mdc`      (auto-attaches to **\/*.{js,ts,...})
 *      - `.cursor/rules/vue.mdc`             (auto-attaches to **\/*.vue)
 *
 *    `all.mdc` is still emitted in this mode but with neutered frontmatter
 *    (no globs, alwaysApply: false) so Cursor will not auto-attach it. It
 *    remains available for tools that explicitly read the file path (e.g.
 *    pb-automation's AI reviewer prompts).
 *
 * In split mode `REPO_GUIDANCE.md` is parsed into per-target sections using
 * HTML-comment markers above each `## Heading`:
 *
 *     <!-- target: php -->
 *     ## Repository Implementation
 *     ...
 *
 * Sections without a marker default to `overview` and emit a build warning.
 */
final class CompileAiGuidance extends Command
{
    private const COMMAND_NAME = 'compile-ai-guidance';

    private const RULES_DIR = '.cursor/rules';
    private const LEGACY_ALL_FILE = self::RULES_DIR . '/all.mdc';

    /** Default mapping from vendor module → split-mode output target. */
    private const DEFAULT_MODULE_TARGETS = [
        'GENERAL'    => 'code-principles',
        'PHP'        => 'php',
        'JAVASCRIPT' => 'javascript',
        'VUE'        => 'vue',
        'SYMFONY'    => 'php',
        'LARAVEL'    => 'php',
    ];

    /** Frontmatter for each split-mode target. */
    private const TARGET_FRONTMATTER = [
        'repo-overview'   => "---\ndescription: Repository orientation (key files/dirs, namespaces, dev workflow). Always loaded.\nalwaysApply: true\n---\n",
        'code-principles' => "---\ndescription: Universal code-quality principles. Loads for any source-code file.\nglobs: **/*.{php,js,ts,mjs,cjs,jsx,tsx,vue}\nalwaysApply: false\n---\n",
        'php'             => "---\ndescription: PHP coding standards and PlotBox PHP conventions. Mandatory when editing PHP files.\nglobs: **/*.php\nalwaysApply: false\n---\n",
        'php-tests'       => "---\ndescription: PHP test conventions (PHPUnit, given/when/then, SUT rules, base classes). Mandatory when editing tests/.\nglobs: tests/**/*.php\nalwaysApply: false\n---\n",
        'javascript'      => "---\ndescription: JavaScript/TypeScript standards and Vitest conventions. Mandatory when editing JS/TS files.\nglobs: **/*.{js,ts,mjs,cjs,jsx,tsx}\nalwaysApply: false\n---\n",
        'vue'             => "---\ndescription: Vue 3 / Composition API standards. Mandatory when editing .vue files.\nglobs: **/*.vue\nalwaysApply: false\n---\n",
    ];

    /** Default target for sections that have no `<!-- target: X -->` marker. */
    private const DEFAULT_TARGET = 'repo-overview';

    /**
     * Tolerated short aliases editors may use in `<!-- target: X -->` markers.
     * Keeps REPO_GUIDANCE.md readable without needing the full filename root.
     */
    private const TARGET_ALIASES = [
        'overview' => 'repo-overview',
        'code'     => 'code-principles',
        'js'       => 'javascript',
        'ts'       => 'javascript',
        'tests'    => 'php-tests',
    ];

    /**
     * @param array<int, string> $modules Vendor guidance modules to include
     *                                    (e.g. ['GENERAL', 'PHP', 'JAVASCRIPT', 'VUE']).
     * @param bool $splitOutputs When true, emit one Cursor rule file per target
     *                           (recommended). When false, only the legacy
     *                           all.mdc is generated.
     */
    public function __construct(
        private readonly array $modules = ['GENERAL', 'PHP', 'JAVASCRIPT'],
        private readonly bool $splitOutputs = false,
    ) {
        parent::__construct();
    }

    /** @inheritDoc */
    protected function configure(): void
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('Compiles AI guidance files for Cursor from source guidance files');
    }

    /** @inheritDoc */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectRoot = Util::getProjectRoot();
        chdir($projectRoot);

        if (!file_exists('REPO_GUIDANCE.md')) {
            $output->writeln('<error>REPO_GUIDANCE.md not found in project root. Aborting.</error>');
            return Command::FAILURE;
        }

        $repoGuidance = file_get_contents($projectRoot . '/REPO_GUIDANCE.md');
        $vendorContents = $this->loadVendorContents($projectRoot);

        $this->ensureDir($projectRoot . '/' . self::RULES_DIR);

        if ($this->splitOutputs) {
            $this->writeSplitOutputs($projectRoot, $repoGuidance, $vendorContents, $output);
        }

        $this->writeLegacyAllMdc($projectRoot, $repoGuidance, $vendorContents, $output);

        $output->writeln('<info>AI guidance files compiled successfully!</info>');

        return Command::SUCCESS;
    }

    /**
     * @return array<string, string> module-name (e.g. 'PHP') => file content
     */
    private function loadVendorContents(string $projectRoot): array
    {
        $contents = [];
        foreach ($this->modules as $module) {
            $module = strtoupper($module);
            $candidates = [
                "vendor/plotbox-io/standards/guidance/$module.md",
                "guidance/$module.md",
            ];
            foreach ($candidates as $relativePath) {
                $absolute = $projectRoot . '/' . $relativePath;
                if (file_exists($absolute)) {
                    $contents[$module] = file_get_contents($absolute);
                    break;
                }
            }
        }
        return $contents;
    }

    /**
     * @param array<string, string> $vendorContents
     */
    private function writeSplitOutputs(
        string $projectRoot,
        string $repoGuidance,
        array $vendorContents,
        OutputInterface $output,
    ): void {
        $repoSections = self::parseRepoGuidance($repoGuidance, $output);

        // Build per-target content: vendor module first, then matching repo sections.
        $perTarget = [];
        foreach (array_keys(self::TARGET_FRONTMATTER) as $target) {
            $perTarget[$target] = '';
        }

        foreach ($vendorContents as $module => $body) {
            $target = self::DEFAULT_MODULE_TARGETS[$module] ?? null;
            if ($target === null || !isset($perTarget[$target])) {
                $output->writeln(
                    "<comment>Skipping vendor module '$module' — no target mapping defined.</comment>",
                );
                continue;
            }
            $perTarget[$target] .= "## Source: vendor/plotbox-io/standards/guidance/$module.md\n\n$body\n\n";
        }

        foreach ($repoSections as $target => $body) {
            if (!isset($perTarget[$target])) {
                $output->writeln(
                    "<comment>Warning: REPO_GUIDANCE.md uses unknown target '$target' — content will be written to repo-overview instead.</comment>",
                );
                $perTarget[self::DEFAULT_TARGET] .= "## Source: REPO_GUIDANCE.md (unknown target '$target')\n\n$body\n\n";
                continue;
            }
            $perTarget[$target] .= "## Source: REPO_GUIDANCE.md\n\n$body\n\n";
        }

        foreach ($perTarget as $target => $body) {
            $body = trim($body);
            if ($body === '') {
                continue;
            }
            $path = $projectRoot . '/' . self::RULES_DIR . '/' . $target . '.mdc';
            $contents = self::TARGET_FRONTMATTER[$target] . self::generatedFileWarning() . $body . "\n";
            file_put_contents($path, $contents);
            $output->writeln("<comment>Updated $path</comment>");
        }
    }

    /**
     * @param array<string, string> $vendorContents
     */
    private function writeLegacyAllMdc(
        string $projectRoot,
        string $repoGuidance,
        array $vendorContents,
        OutputInterface $output,
    ): void {
        $combined = "## Source: REPO_GUIDANCE.md\n\n" . $repoGuidance . "\n\n";
        foreach ($vendorContents as $module => $body) {
            $combined .= "## Source: vendor/plotbox-io/standards/guidance/$module.md\n\n" . $body . "\n\n";
        }

        // In split mode the legacy file is kept around for back-compat consumers
        // (e.g. pb-automation's AI reviewer prompts) but gets neutered frontmatter
        // so Cursor itself will NOT auto-attach it.
        if ($this->splitOutputs) {
            $frontmatter = "---\ndescription: Legacy combined guidance. Cursor IDE does NOT auto-attach this file; it is retained for tools that read the file path explicitly (e.g. pb-automation reviewer prompts). Prefer the per-language rule files.\nalwaysApply: false\n---\n";
        } else {
            $frontmatter = "---\nglobs: **/*\nalwaysApply: false\n---\n";
        }

        $path = $projectRoot . '/' . self::LEGACY_ALL_FILE;
        file_put_contents($path, $frontmatter . self::generatedFileWarning() . $combined);
        $output->writeln("<comment>Updated $path</comment>");
    }

    /**
     * Parse REPO_GUIDANCE.md into target → concatenated section bodies.
     *
     * Rules:
     *   - Every `## Heading` belongs to exactly one target.
     *   - The target is set by the most recent `<!-- target: X -->` HTML comment
     *     appearing on its own line above the heading (with optional blank
     *     lines between).
     *   - Sections with no preceding marker default to `overview` and trigger
     *     a build warning so editors notice unmarked content.
     *   - Content before the first `## ` (preamble) is always treated as
     *     part of the overview target.
     *   - Marker lines themselves are stripped from output.
     *
     * @return array<string, string> target-name => concatenated section body
     */
    public static function parseRepoGuidance(string $content, ?OutputInterface $output = null): array
    {
        $sections = [];
        $currentTarget = self::DEFAULT_TARGET;
        $currentSectionLines = [];
        $pendingTarget = null;
        $pendingHeading = null;

        $flush = static function () use (&$sections, &$currentTarget, &$currentSectionLines): void {
            if ($currentSectionLines === []) {
                return;
            }
            $body = rtrim(implode("\n", $currentSectionLines), "\n");
            if ($body === '') {
                return;
            }
            $sections[$currentTarget] = ($sections[$currentTarget] ?? '') . $body . "\n\n";
            $currentSectionLines = [];
        };

        $lines = preg_split('/\r\n|\r|\n/', $content);
        if ($lines === false) {
            return [];
        }

        foreach ($lines as $line) {
            // Marker comment: capture target for the next ## heading.
            if (preg_match('/^\s*<!--\s*target:\s*(\S+)\s*-->\s*$/', $line, $matches) === 1) {
                $pendingTarget = self::TARGET_ALIASES[$matches[1]] ?? $matches[1];
                continue;
            }

            // New top-level section heading.
            if (preg_match('/^##\s+/', $line) === 1) {
                $flush();

                $newTarget = $pendingTarget;
                if ($newTarget === null) {
                    $newTarget = self::DEFAULT_TARGET;
                    $output?->writeln(
                        "<comment>Warning: REPO_GUIDANCE.md section '$line' has no <!-- target: X --> marker. Defaulting to '" . self::DEFAULT_TARGET . "'.</comment>",
                    );
                }

                $currentTarget = $newTarget;
                $currentSectionLines = [$line];
                $pendingTarget = null;
                $pendingHeading = $line;
                continue;
            }

            // Top-level intro/preamble before the first heading: flush each blank-line
            // separated paragraph into the overview target without emitting a warning
            // about a missing marker (preamble belongs to overview by convention).
            if ($pendingHeading === null && $currentTarget === self::DEFAULT_TARGET) {
                $currentSectionLines[] = $line;
                continue;
            }

            $currentSectionLines[] = $line;
        }

        $flush();

        return $sections;
    }

    private function ensureDir(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    private static function generatedFileWarning(): string
    {
        return "<!--\nDO NOT EDIT THIS FILE DIRECTLY.\n"
            . "It is generated from REPO_GUIDANCE.md and vendor/plotbox-io/standards/guidance/ files.\n"
            . "Run 'bin/dev compile-ai-guidance' to regenerate.\n-->\n\n";
    }
}
