<?php declare(strict_types=1);

namespace PlotBox\Standards\PlotBox\Sniffs\Namespaces;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Disallows `use function` imports for global PHP functions (e.g., `use function strlen;`).
 *
 * With allowFallbackGlobalFunctions enabled, PHP's fallback resolution handles global
 * function calls automatically. Bare imports like `use function strlen;` are redundant.
 * Namespaced function imports (e.g., `use function Safe\json_decode;`) are not affected.
 */
class DisallowGlobalFunctionImportSniff implements Sniff
{
    public const CODE_GLOBAL_FUNCTION_IMPORT = 'GlobalFunctionImport';

    /** @return int[] */
    public function register(): array
    {
        return [T_USE];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$stackPtr]['conditions']) && $tokens[$stackPtr]['conditions'] !== []) {
            return;
        }

        $nextToken = $phpcsFile->findNext(T_WHITESPACE, $stackPtr + 1, null, true);
        if ($nextToken === false) {
            return;
        }

        if (!$this->isFunctionKeyword($tokens, $nextToken)) {
            return;
        }

        $useEnd = $phpcsFile->findNext(T_SEMICOLON, $stackPtr);
        if ($useEnd === false) {
            return;
        }

        $importedName = '';
        for ($j = $nextToken + 1; $j < $useEnd; $j++) {
            if ($tokens[$j]['code'] !== T_WHITESPACE) {
                $importedName .= $tokens[$j]['content'];
            }
        }

        if (str_contains($importedName, '\\')) {
            return;
        }

        $functionName = strtolower($importedName);
        if ($functionName === '') {
            return;
        }

        $fix = $phpcsFile->addFixableError(
            sprintf(
                'Unnecessary import of global function "%s". Global functions are resolved via fallback and do not need a use statement.',
                $importedName
            ),
            $stackPtr,
            self::CODE_GLOBAL_FUNCTION_IMPORT
        );

        if ($fix) {
            $this->removeUseStatement($phpcsFile, $stackPtr, $useEnd);
        }
    }

    private function isFunctionKeyword(array $tokens, int $stackPtr): bool
    {
        return $tokens[$stackPtr]['code'] === T_FUNCTION
            || ($tokens[$stackPtr]['code'] === T_STRING && strtolower($tokens[$stackPtr]['content']) === 'function');
    }

    private function removeUseStatement(File $phpcsFile, int $useStart, int $useEnd): void
    {
        $phpcsFile->fixer->beginChangeset();

        for ($i = $useStart; $i <= $useEnd; $i++) {
            $phpcsFile->fixer->replaceToken($i, '');
        }

        $next = $useEnd + 1;
        $tokens = $phpcsFile->getTokens();
        if (isset($tokens[$next]) && $tokens[$next]['code'] === T_WHITESPACE && $tokens[$next]['content'] === "\n") {
            $phpcsFile->fixer->replaceToken($next, '');
        }

        $phpcsFile->fixer->endChangeset();
    }
}
