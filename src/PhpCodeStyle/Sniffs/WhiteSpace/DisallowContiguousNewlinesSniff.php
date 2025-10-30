<?php

namespace PlotBox\PhpCodeStyle\Sniffs\WhiteSpace;

/**
 * This is different from the Squiz superfluous whitespace sniff in that it detects contiguous newlines outside
 * of functions as well as inside them
 */

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class DisallowContiguousNewlinesSniff implements Sniff
{
    public function register()
    {
        return [T_WHITESPACE];
    }

    /**
     *
     * @param array $tokens
     * @param int $stackPtr
     * @return bool
     */
    private function isThisTheLastTokenOnLine(array $tokens, $stackPtr)
    {
        $currentLineNumber = $tokens[$stackPtr]['line'];

        $isThisTheSecondToLastTokenInFile = count($tokens) == $stackPtr + 1;

        if ($isThisTheSecondToLastTokenInFile) {
            return true;
        }

        $nextTokensLineNumber = $tokens[$stackPtr + 1]['line'];

        return $nextTokensLineNumber > $currentLineNumber;
    }

    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        if (!$this->isThisTheLastTokenOnLine($tokens, $stackPtr)) {
            return;
        }

        $prevNonWhitespaceTokenIndex = $phpcsFile->findPrevious(T_WHITESPACE, $stackPtr - 1, null, true);
        $currentLineNumber = $tokens[$stackPtr]['line'];

        $prevNonWhitespaceToken = $tokens[$prevNonWhitespaceTokenIndex];

        $numberOfLinesBetweenLastNonWhiteSpaceTokenAndThisOne
            = $currentLineNumber - $prevNonWhitespaceToken['line'];

        if ($numberOfLinesBetweenLastNonWhiteSpaceTokenAndThisOne > 1) {
            $phpcsFile->addError("Contiguous blank lines found", $stackPtr, 'ContiguousNewlines');
        }
    }

}
