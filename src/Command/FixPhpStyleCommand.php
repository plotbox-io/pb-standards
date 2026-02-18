<?php

declare(strict_types=1);

namespace PlotBox\Standards\Command;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

final class FixPhpStyleCommand extends Command
{
    private const string COMMAND_NAME = 'fix-php-style';

    private string $projectRoot;

    public function __construct()
    {
        parent::__construct(self::COMMAND_NAME);
        $this->projectRoot = getcwd();
    }

    /** @inheritdoc */
    protected function configure(): void
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->addArgument(
                'target',
                InputArgument::REQUIRED,
                'File path to target the style fixes at (relative to project root, or absolute)'
            )
            ->setDescription(
                'Automatically fix code style for a PHP file or a directory containing PHP files'
            );
    }

    /** @inheritdoc */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $targetPath = $input->getArgument('target');
        $resolvedTarget = $this->resolveTargetPath($targetPath);

        if (!file_exists($resolvedTarget)) {
            throw new RuntimeException("File at path '$resolvedTarget' did not exist. Check path");
        }

        $fixCommands = $this->buildFixCommands($resolvedTarget);

        if ($fixCommands === []) {
            $output->writeln('<comment>No style fixers available. Ensure php-cs-fixer or phpcbf is installed.</comment>');
            return self::SUCCESS;
        }

        $outputStyle = new OutputFormatterStyle(options: ['bold']);
        $output->getFormatter()->setStyle('bold', $outputStyle);

        $exitCodes = [];
        foreach ($fixCommands as $label => $shellCommand) {
            $process = Process::fromShellCommandline($shellCommand, $this->projectRoot, [
                'XDEBUG_MODE' => 'off',
            ]);
            $spinner = new SpinnerProgress("<bold>$label</bold>", $output);
            $process->start();

            while ($process->isRunning()) {
                $spinner->advance();
                usleep(5000);
            }

            $exitCodes[$label] = $process->getExitCode();
            $success = $exitCodes[$label] === 0;
            $spinner->finish($success);
            if (!$success) {
                $output->writeln('<error>' . $process->getOutput() . '</error>');
            }
        }

        $output->writeln(PHP_EOL);

        $allSucceeded = !in_array(false, array_map(fn (int $code) => $code === 0, $exitCodes), true);

        if (!$allSucceeded) {
            $summary = implode(', ', array_map(
                fn (string $label, int $code) => "$label: $code",
                array_keys($exitCodes),
                array_values($exitCodes)
            ));
            $output->writeln("<error>At least one fixer process failed ($summary)</error>");
        } else {
            $output->writeln('<info>Completed successfully!</info>');
        }

        return $allSucceeded ? self::SUCCESS : self::FAILURE;
    }

    /** @return array<string, string> */
    private function buildFixCommands(string $resolvedTarget): array
    {
        $commands = [];

        $csFixerBin = $this->projectRoot . '/vendor/bin/php-cs-fixer';
        if (file_exists($csFixerBin)) {
            $commands['PHP CS Fixer'] = $this->makeCsFixerShellCommand($resolvedTarget);
        }

        $phpcbfBin = $this->projectRoot . '/vendor/bin/phpcbf';
        if (file_exists($phpcbfBin)) {
            $commands['Code Sniffer (phpcbf)'] = $this->makePhpcbfShellCommand($resolvedTarget);
        }

        return $commands;
    }

    private function makeCsFixerShellCommand(string $resolvedTarget): string
    {
        $configFile = $this->projectRoot . '/.php-cs-fixer.dist.php';
        $configFlag = file_exists($configFile) ? ' --config=' . escapeshellarg($configFile) : '';

        return 'php vendor/bin/php-cs-fixer fix' . $configFlag . ' ' . escapeshellarg($resolvedTarget);
    }

    private function makePhpcbfShellCommand(string $resolvedTarget): string
    {
        $errorMode = E_ERROR | E_PARSE;

        $exclusions = [
            'SlevomatCodingStandard.Operators.DisallowEqualOperators',
            'Squiz.Commenting.PostStatementComment',
        ];

        return "php -d error_reporting=$errorMode vendor/bin/phpcbf"
            . ' --standard=PlotBox'
            . ' --exclude=' . implode(',', $exclusions)
            . ' ' . escapeshellarg($resolvedTarget);
    }

    private function resolveTargetPath(string $targetPath): string
    {
        if (str_starts_with($targetPath, '/')) {
            return $targetPath;
        }

        return $this->projectRoot . '/' . $targetPath;
    }
}
