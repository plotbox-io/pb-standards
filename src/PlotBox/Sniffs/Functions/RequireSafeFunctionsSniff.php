<?php declare(strict_types=1);

namespace PlotBox\Standards\PlotBox\Sniffs\Functions;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Enforces use of Safe\function_name over the native PHP function where a Safe equivalent exists.
 *
 * The thecodingmachine/safe package provides wrapper functions that throw exceptions instead of
 * returning false on failure. This sniff detects calls to native PHP functions that have a Safe
 * equivalent and are not already imported via `use function Safe\...`.
 */
class RequireSafeFunctionsSniff implements Sniff
{
    public const CODE_UNSAFE_FUNCTION = 'UnsafeFunction';

    /** @var array<string, true>|null */
    private static ?array $safeFunctions = null;

    /**
     * Functions to exclude from enforcement.
     * Configure via ruleset.xml: <property name="excludedFunctions" type="array">
     *
     * @var string[]
     */
    public array $excludedFunctions = [];

    /** @var array<string, true> */
    private array $excludedFunctionsMap = [];

    /** @var array<string, true>|null Cached Safe imports for the current file */
    private ?array $safeImports = null;

    private string $currentFile = '';

    /** @return int[] */
    public function register(): array
    {
        return [T_STRING];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $filename = $phpcsFile->getFilename();
        if ($filename !== $this->currentFile) {
            $this->currentFile = $filename;
            $this->safeImports = null;
        }

        $tokens = $phpcsFile->getTokens();
        $functionName = strtolower($tokens[$stackPtr]['content']);

        if (!$this->isSafeFunction($functionName)) {
            return;
        }

        if ($this->isExcluded($functionName)) {
            return;
        }

        if (!$this->isFunctionCall($phpcsFile, $stackPtr)) {
            return;
        }

        if ($this->hasSafeImport($phpcsFile, $tokens[$stackPtr]['content'])) {
            return;
        }

        $phpcsFile->addError(
            sprintf(
                'Function %s() has a Safe equivalent. Use "use function Safe\%s;" and call %s() instead.',
                $tokens[$stackPtr]['content'],
                $tokens[$stackPtr]['content'],
                $tokens[$stackPtr]['content']
            ),
            $stackPtr,
            self::CODE_UNSAFE_FUNCTION
        );
    }

    private function isSafeFunction(string $functionName): bool
    {
        $functions = $this->getSafeFunctions();
        return isset($functions[$functionName]);
    }

    private function isExcluded(string $functionName): bool
    {
        if ($this->excludedFunctionsMap === [] && $this->excludedFunctions !== []) {
            $this->excludedFunctionsMap = array_flip($this->excludedFunctions);
        }

        return isset($this->excludedFunctionsMap[$functionName]);
    }

    /**
     * Determines whether the T_STRING at $stackPtr is actually a function call
     * (not a method call, function definition, class name, constant, etc.)
     */
    private function isFunctionCall(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        $nextNonWhitespace = $phpcsFile->findNext(T_WHITESPACE, $stackPtr + 1, null, true);
        if ($nextNonWhitespace === false || $tokens[$nextNonWhitespace]['code'] !== T_OPEN_PARENTHESIS) {
            return false;
        }

        $prevNonWhitespace = $phpcsFile->findPrevious(T_WHITESPACE, $stackPtr - 1, null, true);
        if ($prevNonWhitespace === false) {
            return false;
        }

        $prevCode = $tokens[$prevNonWhitespace]['code'];

        $nonCallTokens = [
            T_FUNCTION,
            T_OBJECT_OPERATOR,
            T_NULLSAFE_OBJECT_OPERATOR,
            T_DOUBLE_COLON,
            T_NEW,
            T_NS_SEPARATOR,
        ];

        if (in_array($prevCode, $nonCallTokens, true)) {
            return false;
        }

        return true;
    }

    private function hasSafeImport(File $phpcsFile, string $functionName): bool
    {
        if ($this->safeImports === null) {
            $this->safeImports = $this->parseSafeImports($phpcsFile);
        }

        return isset($this->safeImports[strtolower($functionName)]);
    }

    /** @return array<string, true> */
    private function parseSafeImports(File $phpcsFile): array
    {
        $tokens = $phpcsFile->getTokens();
        $imports = [];

        for ($i = 0; $i < $phpcsFile->numTokens; $i++) {
            if ($tokens[$i]['code'] !== T_USE) {
                continue;
            }

            if (isset($tokens[$i]['conditions']) && $tokens[$i]['conditions'] !== []) {
                continue;
            }

            $useEnd = $phpcsFile->findNext(T_SEMICOLON, $i);
            if ($useEnd === false) {
                continue;
            }

            $content = '';
            for ($j = $i + 1; $j < $useEnd; $j++) {
                if ($tokens[$j]['code'] !== T_WHITESPACE) {
                    $content .= $tokens[$j]['content'];
                }
            }

            $content = strtolower($content);

            if (preg_match('/^functionSafe\\\\(.+)$/i', $content, $matches) === 1) {
                $imports[$matches[1]] = true;
            }
        }

        return $imports;
    }

    /** @return array<string, true> */
    private function getSafeFunctions(): array
    {
        if (self::$safeFunctions !== null) {
            return self::$safeFunctions;
        }

        $safeFunctionsDir = $this->findSafeGeneratedDir();
        if ($safeFunctionsDir === null) {
            self::$safeFunctions = [];
            return self::$safeFunctions;
        }

        $phpVersion = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        $versionFile = $safeFunctionsDir . '/' . $phpVersion . '/functionsList.php';

        if (!file_exists($versionFile)) {
            $available = glob($safeFunctionsDir . '/*/functionsList.php');
            if ($available === false || $available === []) {
                self::$safeFunctions = [];
                return self::$safeFunctions;
            }

            $versionFile = end($available);
        }

        /** @var string[] $list */
        $list = require $versionFile;
        self::$safeFunctions = array_flip($list);

        return self::$safeFunctions;
    }

    private function findSafeGeneratedDir(): ?string
    {
        $reflection = new \ReflectionClass(\Safe\Exceptions\SafeExceptionInterface::class);
        $packageRoot = dirname((string) $reflection->getFileName(), 3);
        $generatedDir = $packageRoot . '/generated';

        if (is_dir($generatedDir)) {
            return $generatedDir;
        }

        return null;
    }
}
