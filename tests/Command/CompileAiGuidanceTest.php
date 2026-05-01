<?php

declare(strict_types=1);

namespace PlotBox\Standards\Tests\Command;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PlotBox\Standards\Command\CompileAiGuidance;
use Symfony\Component\Console\Output\BufferedOutput;

final class CompileAiGuidanceTest extends TestCase
{
    #[Test]
    public function should_route_marked_sections_to_their_target(): void
    {
        $input = <<<'MD'
            <!-- target: overview -->
            ## Key Files
            - foo.php
            - bar.php

            <!-- target: php -->
            ## Repository Implementation
            - prefer GCD over raw SQL

            <!-- target: php-tests -->
            ## PHP Tests
            - extend PlotboxUnitTest

            <!-- target: javascript -->
            ## JS Notes
            - use Vitest
            MD;

        $sections = CompileAiGuidance::parseRepoGuidance($input);

        self::assertArrayHasKey('repo-overview', $sections);
        self::assertArrayHasKey('php', $sections);
        self::assertArrayHasKey('php-tests', $sections);
        self::assertArrayHasKey('javascript', $sections);

        self::assertStringContainsString('## Key Files', $sections['repo-overview']);
        self::assertStringContainsString('foo.php', $sections['repo-overview']);
        self::assertStringNotContainsString('Repository Implementation', $sections['repo-overview']);

        self::assertStringContainsString('## Repository Implementation', $sections['php']);
        self::assertStringContainsString('prefer GCD over raw SQL', $sections['php']);

        self::assertStringContainsString('## PHP Tests', $sections['php-tests']);
        self::assertStringContainsString('extend PlotboxUnitTest', $sections['php-tests']);

        self::assertStringContainsString('## JS Notes', $sections['javascript']);
    }

    #[Test]
    public function should_strip_target_marker_lines_from_output(): void
    {
        $input = <<<'MD'
            <!-- target: php -->
            ## Some Section
            content
            MD;

        $sections = CompileAiGuidance::parseRepoGuidance($input);

        self::assertArrayHasKey('php', $sections);
        self::assertStringNotContainsString('<!-- target: php -->', $sections['php']);
    }

    #[Test]
    public function should_default_unmarked_sections_to_repo_overview_with_warning(): void
    {
        $input = <<<'MD'
            ## Unmarked Section
            content here
            MD;

        $output = new BufferedOutput();
        $sections = CompileAiGuidance::parseRepoGuidance($input, $output);

        self::assertArrayHasKey('repo-overview', $sections);
        self::assertStringContainsString('## Unmarked Section', $sections['repo-overview']);

        $warningText = $output->fetch();
        self::assertStringContainsString('Unmarked Section', $warningText);
        self::assertStringContainsString("no <!-- target: X --> marker", $warningText);
    }

    #[Test]
    public function should_treat_preamble_before_first_heading_as_overview(): void
    {
        $input = <<<'MD'
            This is a top-level intro paragraph.
            Multiple lines are fine.

            <!-- target: php -->
            ## PHP Section
            php content
            MD;

        $sections = CompileAiGuidance::parseRepoGuidance($input);

        self::assertArrayHasKey('repo-overview', $sections);
        self::assertStringContainsString('top-level intro paragraph', $sections['repo-overview']);
        self::assertArrayHasKey('php', $sections);
        self::assertStringContainsString('## PHP Section', $sections['php']);
    }

    #[Test]
    public function should_concatenate_multiple_sections_with_the_same_target(): void
    {
        $input = <<<'MD'
            <!-- target: php -->
            ## First PHP Section
            first body

            <!-- target: php -->
            ## Second PHP Section
            second body
            MD;

        $sections = CompileAiGuidance::parseRepoGuidance($input);

        self::assertArrayHasKey('php', $sections);
        self::assertStringContainsString('## First PHP Section', $sections['php']);
        self::assertStringContainsString('## Second PHP Section', $sections['php']);
        self::assertStringContainsString('first body', $sections['php']);
        self::assertStringContainsString('second body', $sections['php']);
    }

    #[Test]
    public function should_ignore_subheadings_for_target_routing(): void
    {
        $input = <<<'MD'
            <!-- target: php -->
            ## Top-Level
            ### Subheading That Should Stay In Php
            still php content
            #### Even Deeper
            still php
            MD;

        $sections = CompileAiGuidance::parseRepoGuidance($input);

        self::assertArrayHasKey('php', $sections);
        self::assertStringContainsString('### Subheading That Should Stay In Php', $sections['php']);
        self::assertStringContainsString('#### Even Deeper', $sections['php']);
    }

    #[Test]
    public function should_handle_empty_input(): void
    {
        self::assertSame([], CompileAiGuidance::parseRepoGuidance(''));
    }

    #[Test]
    public function should_strip_preamble_html_comment_blocks(): void
    {
        $input = <<<'MD'
            <!--
            EDITOR NOTE: this is a multi-line comment about how to use this file.
            It should NOT appear in any compiled rule file.
            -->

            <!-- a single-line preamble comment to also strip -->

            <!-- target: php -->
            ## PHP Section
            real php content
            MD;

        $sections = CompileAiGuidance::parseRepoGuidance($input);

        self::assertArrayHasKey('php', $sections);
        self::assertStringContainsString('## PHP Section', $sections['php']);
        self::assertStringContainsString('real php content', $sections['php']);

        self::assertArrayNotHasKey('repo-overview', $sections);
        foreach ($sections as $body) {
            self::assertStringNotContainsString('EDITOR NOTE', $body);
            self::assertStringNotContainsString('single-line preamble comment', $body);
        }
    }

    #[Test]
    public function should_resolve_short_aliases_in_markers(): void
    {
        $input = <<<'MD'
            <!-- target: overview -->
            ## Some Overview
            content

            <!-- target: code -->
            ## Code Block
            content

            <!-- target: tests -->
            ## Test Block
            content

            <!-- target: js -->
            ## JS Block
            content
            MD;

        $sections = CompileAiGuidance::parseRepoGuidance($input);

        self::assertArrayHasKey('repo-overview', $sections);
        self::assertArrayHasKey('code-principles', $sections);
        self::assertArrayHasKey('php-tests', $sections);
        self::assertArrayHasKey('javascript', $sections);
    }

    #[Test]
    public function should_handle_whitespace_around_marker(): void
    {
        $input = <<<'MD'
            <!--   target:   php   -->
            ## Spaced Marker
            body
            MD;

        $sections = CompileAiGuidance::parseRepoGuidance($input);

        self::assertArrayHasKey('php', $sections);
        self::assertStringContainsString('Spaced Marker', $sections['php']);
    }
}
