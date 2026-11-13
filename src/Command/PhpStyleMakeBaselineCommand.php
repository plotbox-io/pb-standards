<?php

declare(strict_types=1);

namespace PlotBox\Standards\Command;

use PlotBox\Standards\Baseline\LooseBaseline;
use PlotBox\Standards\Baseline\PhpcsBaselineIssues;
use PlotBox\Standards\Util\Shell;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Safe\passthru;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

final class PhpStyleMakeBaselineCommand extends Command
{
    private const COMMAND_NAME = 'php-style-baseline';

    private string $appRoot;

    public function __construct(
        private ?string $phpVersion = null
    ) {
        parent::__construct(self::COMMAND_NAME);
        $envRoot = getenv('PB_PROJECT_ROOT');
        $this->appRoot = is_string($envRoot) && $envRoot !== '' ? $envRoot : getcwd();
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
                name: 'baseline',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Baseline file to write. A .toml path writes a loose baseline; anything else uses SARB.'
            )
            ->addOption(
                name: 'src',
                mode: InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                description: 'Source directories to check (can be specified multiple times)'
            )
            ->setDescription('Check PHP code style');
    }

    /** @inheritdoc */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        ini_set('memory_limit', '-1');
        chdir($this->appRoot);
        $io = new SymfonyStyle($input, $output);

        if ($this->phpVersion) {
            $phpVersion = $this->phpVersion;
        } else {
            $phpVersion = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        }

        $explicit = $input->getOption('baseline');
        $sarbBaselinePath = is_string($explicit) && $explicit !== ''
            ? $explicit
            : ($input->getOption('sarb-baseline') ?: $this->appRoot . '/phpcs.baseline');
        $srcDirs = $input->getOption('src');
        if (!$srcDirs || count($srcDirs) === 0) {
            $io->error('At least one source directory must be specified using the --src option.');
            return Command::FAILURE;
        }
        $srcDirsString = implode(' ', array_map('escapeshellarg', $srcDirs));
        $phpcsCommand = <<<CMD
            php -d memory_limit=-1 -d error_reporting=5 vendor/bin/phpcs \
                --runtime-set testVersion $phpVersion \
                --extensions=php \
                --parallel=16 \
                --standard=Plotbox \
                --report=json \
                $srcDirsString
            CMD;

        if (str_ends_with($sarbBaselinePath, '.toml')) {
            $issues = PhpcsBaselineIssues::fromJsonReport(
                (string) Shell::exec($phpcsCommand)->stdOut,
                ['/opt/project/', '/app/', $this->appRoot . '/']
            );
            file_put_contents($sarbBaselinePath, LooseBaseline::fromIssues($issues)->toToml());
            $io->success(sprintf('Wrote %d issues to %s', count($issues), $sarbBaselinePath));
            return Command::SUCCESS;
        }

        $command = <<<CMD
            $phpcsCommand \
            | php -d memory_limit=-1 \
                ./vendor/bin/sarb create \
                --input-format="phpcodesniffer-json" \
                $sarbBaselinePath
            CMD;

        passthru($command, $exitCode);
        return $exitCode;
    }
}
