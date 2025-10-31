<?php

declare(strict_types=1);

namespace PlotBox\Standards\PlotBox\Sniffs\Commenting;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TypelessParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use SlevomatCodingStandard\Helpers\Annotation;
use SlevomatCodingStandard\Helpers\DocCommentHelper;
use SlevomatCodingStandard\Helpers\FunctionHelper;
use SlevomatCodingStandard\Helpers\TypeHint;
use SlevomatCodingStandard\Helpers\TypeHintHelper;

use function array_key_exists;
use function sprintf;
use const T_FUNCTION;

class DisallowSingleTypedArraySniff implements Sniff
{
    public const CODE_SINGLE_TYPED_ARRAY = 'SingleTypedArray';

    /** @return array<int, (int|string)> */
    public function register(): array
    {
        return [T_FUNCTION];
    }

    /**
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
     * @param int $functionPointer
     * @noinspection PhpParameterNameChangedDuringInheritanceInspection
     */
    public function process(File $phpcsFile, $functionPointer): void
    {
        if (!DocCommentHelper::hasDocComment($phpcsFile, $functionPointer)) {
            return;
        }

        if (DocCommentHelper::hasInheritdocAnnotation($phpcsFile, $functionPointer)) {
            return;
        }

        if (DocCommentHelper::hasDocCommentDescription($phpcsFile, $functionPointer)) {
            return;
        }

        $returnTypeHint = FunctionHelper::findReturnTypeHint($phpcsFile, $functionPointer);
        $returnAnnotation = FunctionHelper::findReturnAnnotation($phpcsFile, $functionPointer);

        if ($returnAnnotation && $this->isArrayTypeWithoutBothGenericTypesDefined(
                $phpcsFile,
                $functionPointer,
                $returnTypeHint,
                $returnAnnotation)) {
            $phpcsFile->addError(
                sprintf(
                /** @lang text */ '%s %s() array type must declare both KEY and VALUE types (i.e., array<type, type>). Or maybe you meant like list<type>?',
                    FunctionHelper::getTypeLabel($phpcsFile, $functionPointer),
                    FunctionHelper::getFullyQualifiedName($phpcsFile, $functionPointer)
                ),
                $functionPointer,
                self::CODE_SINGLE_TYPED_ARRAY
            );
        }

        $parameterTypeHints = FunctionHelper::getParametersTypeHints($phpcsFile, $functionPointer);
        $parametersAnnotations = FunctionHelper::getValidParametersAnnotations($phpcsFile, $functionPointer);

        foreach ($parametersAnnotations as $parameterName => $parameterAnnotation) {
            if (!array_key_exists($parameterName, $parameterTypeHints)) {
                return;
            }

            if ($this->isArrayTypeWithoutBothGenericTypesDefined(
                $phpcsFile,
                $functionPointer,
                $parameterTypeHints[$parameterName],
                $parameterAnnotation
            )) {
                $phpcsFile->addError(
                    sprintf(
                    /** @lang text */ '%s %s() - array type must declare both KEY and VALUE types (i.e., array<type, type>). Or maybe you meant like list<type>?',
                        FunctionHelper::getTypeLabel($phpcsFile, $functionPointer),
                        FunctionHelper::getFullyQualifiedName($phpcsFile, $functionPointer)
                    ),
                    $functionPointer,
                    self::CODE_SINGLE_TYPED_ARRAY
                );
            }
        }
    }

    private function isArrayTypeWithoutBothGenericTypesDefined(
        File $phpcsFile,
        int $functionPointer,
        ?TypeHint $typeHint,
        Annotation $annotation
    ): bool {
        if ($annotation->isInvalid()) {
            return false;
        }

        if ($typeHint === null) {
            return false;
        }

        /** @var ParamTagValueNode|TypelessParamTagValueNode|ReturnTagValueNode|VarTagValueNode $annotationValue */
        $annotationValue = $annotation->getValue();

        if ($annotationValue->description !== '') {
            return false;
        }

        if ($annotationValue instanceof TypelessParamTagValueNode) {
            return false;
        }

        $fullyQualifiedTypeHint = TypeHintHelper::getFullyQualifiedTypeHint(
            $phpcsFile,
            $functionPointer,
            $typeHint->getTypeHintWithoutNullabilitySymbol()
        );
        $isTraversable = TypeHintHelper::isTraversableType(
            $fullyQualifiedTypeHint,
            []
        );
        if (!$isTraversable) {
            return false;
        }

        /** @var GenericTypeNode $type */
        $type = $annotationValue->type;
        if (!isset($type->type)) {
            return false;
        }
        if ($type->type->name !== 'array') {
            return false;
        }

        return count($type?->genericTypes ?: []) !== 2;
    }
}
