<?php

declare(strict_types=1);

namespace PlotBox\Standards\Command;

use PlotBox\PhpGitOps\Git\Git;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Safe\passthru;

final class PhpStyleMakeBaselineCommand extends Command
{
    private const string COMMAND_NAME = 'php-style-baseline';

    private string $appRoot;

    public function __construct()
    {
        parent::__construct(self::COMMAND_NAME);
        $this->appRoot = getcwd();
        $this->git = new Git($this->appRoot);
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
            ->setDescription('Check PHP code style');
    }

    /** @inheritdoc */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        ini_set('memory_limit', '-1');
        chdir($this->appRoot);
        $sarbBaselinePath = $input->getOption('sarb-baseline') ?: $this->appRoot . '/phpcs.baseline';
        $command = <<<CMD
            php -d memory_limit=-1 -d error_reporting=5 vendor/bin/phpcs \
                --runtime-set testVersion 8.4 \
                --extensions=php \
                --parallel=16 \
                --standard=Plotbox \
                --report=json \
                tests classes libraries/core/modules \
            | php -d memory_limit=-1 \
                ./vendor/bin/sarb create \
                --input-format="phpcodesniffer-json" \
                $sarbBaselinePath
            CMD;

        passthru($command, $exitCode);
        return $exitCode;
    }
}
