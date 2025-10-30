<?php declare(strict_types=1);

namespace PlotBox\PhpCodeStyle\Sniffs\Commenting;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use SlevomatCodingStandard\Helpers\TokenHelper;
use const T_FUNCTION;

class PhpDocInsideFunctionParamsSniff implements Sniff
{
    public const CODE_SINGLE_TYPED_ARRAY = 'PhpDocInsideFunctionParams';

    /**
     * @return array<int, (int|string)>
     */
    public function register(): array
    {
        return [
            T_FUNCTION
        ];
    }

    /**
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
     * @param int $functionPointer
     * @noinspection PhpParameterNameChangedDuringInheritanceInspection
     */
    public function process(File $phpcsFile, $functionPointer): void
    {
        $functionOpenBracketPointer = TokenHelper::findNext(
            $phpcsFile,
            [T_OPEN_CURLY_BRACKET],
            $functionPointer - 1
        );

        // If semicolon is found before the open bracket, it's an abstract function or interface
        // and we don't need to check it..
        $semicolonPointer = TokenHelper::findNext(
            $phpcsFile,
            [T_SEMICOLON],
            $functionPointer - 1
        );
        if( $semicolonPointer !== null && $semicolonPointer < $functionOpenBracketPointer ) {
            return;
        }

        $tokens = $phpcsFile->getTokens();
        for($i=$functionPointer; $i<$functionOpenBracketPointer; $i++) {
            if ($tokens[$i]['code'] === T_DOC_COMMENT_OPEN_TAG) {
                $phpcsFile->addError(
                    'Docblock found inside the function parameters. Docblock must be above the function.',
                    $i,
                    self::CODE_SINGLE_TYPED_ARRAY
                );
            }
        }
    }
}
