<?php

declare(strict_types=1);

namespace PlotBox\Standards\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Safe\passthru;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

final class PhpStyleMakeBaselineCommand extends Command
{
    private const string COMMAND_NAME = 'php-style-baseline';

    private string $appRoot;

    public function __construct(
        private ?string $phpVersion = null
    ) {
        parent::__construct(self::COMMAND_NAME);
        $this->appRoot = getcwd();
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

        $sarbBaselinePath = $input->getOption('sarb-baseline') ?: $this->appRoot . '/phpcs.baseline';
        $srcDirs = $input->getOption('src');
        if (!$srcDirs || count($srcDirs) === 0) {
            $io->error('At least one source directory must be specified using the --src option.');
            return Command::FAILURE;
        }
        $srcDirsString = implode(' ', array_map('escapeshellarg', $srcDirs));
        $command = <<<CMD
            php -d memory_limit=-1 -d error_reporting=5 vendor/bin/phpcs \
                --runtime-set testVersion $phpVersion \
                --extensions=php \
                --parallel=16 \
                --standard=Plotbox \
                --report=json \
                $srcDirsString \
            | php -d memory_limit=-1 \
                ./vendor/bin/sarb create \
                --input-format="phpcodesniffer-json" \
                $sarbBaselinePath
            CMD;

        passthru($command, $exitCode);
        return $exitCode;
    }
}
