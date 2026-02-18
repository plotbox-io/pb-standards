<?php

declare(strict_types=1);

namespace PlotBox\Standards\Command;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

use function Safe\preg_replace;

/** @see https://github.com/icanhazstring/symfony-console-spinner/blob/master/src/SpinnerProgress.php */
class SpinnerProgress
{
    private const int MAX_STDOUT_LINE_LENGTH = 120;
    private const int MAX_KEPT_OUTPUT_CHARACTERS = 300;
    private const string NEWLINE_SEPARATOR = ' >> ';
    private const int PADDING_SPACES_AFTER_MAIN_MESSAGE = 6;
    private const array CHARS = ['⠏', '⠛', '⠹', '⢸', '⣰', '⣤', '⣆', '⡇'];

    private ProgressBar $progressBar;
    private int $step;
    private bool $isFinished;
    private string $output;

    public function __construct(private string $mainMessage, OutputInterface $output)
    {
        $this->progressBar = new ProgressBar($output->section());
        $this->progressBar->setFormat('%bar% %message%');
        $this->progressBar->setBarWidth(1);
        $this->progressBar->setRedrawFrequency(31);
        $this->progressBar->setMessage($this->mainMessage);
        $this->step = 1;
        $this->isFinished = false;
        $this->progressBar->setProgressCharacter('>');
        $this->progressBar->start();
        $this->output = '';
    }

    // phpcs:ignore CognitiveComplexity.Complexity.MaximumComplexity.TooHigh
    public function updateMessageStdOut(string $stdOut, int $maxMainMessageLength): void
    {
        $stdOut = $this->cleanStdOut($stdOut);
        if (!trim($stdOut)) {
            return;
        }

        $this->output .= self::NEWLINE_SEPARATOR . $stdOut;

        if (strlen($this->output) > self::MAX_KEPT_OUTPUT_CHARACTERS) {
            $this->output = substr($this->output, -self::MAX_KEPT_OUTPUT_CHARACTERS);
        }

        $charactersBeforeStdout = $maxMainMessageLength + self::PADDING_SPACES_AFTER_MAIN_MESSAGE;
        $remainingSpace = self::MAX_STDOUT_LINE_LENGTH - $charactersBeforeStdout;

        $displayText = substr($this->output, -$remainingSpace);

        $paddingBeforeCount = $charactersBeforeStdout - strlen(strip_tags($this->mainMessage));
        $paddingAfterCount = $remainingSpace - strlen($displayText);
        $displayText .= str_repeat(' ', $paddingAfterCount);
        $paddingBefore = str_repeat(' ', $paddingBeforeCount);

        $this->progressBar->setMessage($this->mainMessage . "<fg=gray>$paddingBefore| $displayText |</>");
    }

    public function advance(int $step = 1): void
    {
        $this->step += $step;
        $this->progressBar->setProgressCharacter(self::CHARS[$this->step % 8]);
        $this->progressBar->advance($step);
    }

    public function finish(bool $success, bool $wasCache = false): void
    {
        $symbol = '✔';
        if ($wasCache) {
            $symbol = '💾';
        }
        $finishCharacter = $success ? "<fg=green>$symbol</>" : '<fg=red>x</>';
        $this->progressBar->setBarCharacter($finishCharacter);
        $this->progressBar->finish();
        $this->isFinished = true;
    }

    public function isFinished(): bool
    {
        return $this->isFinished;
    }

    public function getMainMessage(): string
    {
        return $this->mainMessage;
    }

    private function cleanStdOut(string $stdOut): string
    {
        $stdOut = preg_replace('/\e[[A-Za-z\d];?\d*m?/', '', $stdOut);
        /** @var string $stdOut */
        $stdOut = str_replace("\r", ' ', $stdOut);
        $stdOut = str_replace("\n", self::NEWLINE_SEPARATOR, $stdOut);
        $stdOut = str_replace("\t", ' ', $stdOut);

        $stdOut = preg_replace('#[^A-Za-z0-9 \[\]_/|]#', '', $stdOut);

        /** @var string $stdOut */
        $stdOut = preg_replace('!\s+!', ' ', $stdOut);
        return $stdOut;
    }
}
