<?php declare(strict_types=1);

namespace PlotBox\Standards\PlotBox\Sniffs\Commenting;

use Exception;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use SlevomatCodingStandard\Helpers\Comment;
use SlevomatCodingStandard\Helpers\DocCommentHelper;
use const T_DOC_COMMENT_OPEN_TAG;

/**
 * Sniff that automatically detects useless comment descriptions - specifically descriptions
 * that are simply the same name as the method/class they are 'describing'
 */
class UselessDescriptionSniff implements Sniff
{
    public const CODE_COMMENT_FORBIDDEN = 'CommentForbidden';

    /** @var Comment[]|null */
    private $lastComments;

    /**
     * @return string[]
     */
    public function register(): array
    {
        return [
            T_DOC_COMMENT_OPEN_TAG,
            T_CLASS,
            T_FUNCTION,
            T_VARIABLE
        ];
    }

    /**
     * @param File $phpcsFile
     * @param int $docCommentOpenPointer
     * @throws Exception
     */
    public function process(File $phpcsFile, $docCommentOpenPointer): void
    {
        $tokens = $phpcsFile->getTokens();
        $tokenType = $tokens[$docCommentOpenPointer]['code'];

        // Capture comments and store for checking later
        if ($tokenType === T_DOC_COMMENT_OPEN_TAG) {
            $comments = DocCommentHelper::getDocCommentDescription($phpcsFile, $docCommentOpenPointer);
            if ($comments === null) {
                return;
            }
            $this->lastComments = $comments;
            return;
        }

        $targetName = null;
        $targetType = null;
        if (in_array($tokenType, [T_CLASS, T_FUNCTION], true)) {
            $targetName = $phpcsFile->getDeclarationName($docCommentOpenPointer);
            $targetType = ($tokenType === T_CLASS) ? 'class' : 'function';
        } else {
            $targetName = str_replace('$', '', $tokens[$docCommentOpenPointer]['content']);
            $targetType = 'variable';
        }

        // Ignore any until we have another docBlock comment
        if ($this->lastComments === null) {
            return;
        }

        // Should probably ignore if it is 'too far away' (not sure how to measure this..). I think is
        // unlikely there'll ever be any false positives for this check though so prob not needed..
        foreach ($this->lastComments as $lastComment) {
            if ($this->compare($targetType, $lastComment->getContent(), $targetName)) {
                $phpcsFile->addError(
                    sprintf('Useless documentation comment containing only the name of the %s: "%s".', $targetType, $lastComment->getContent()),
                    $lastComment->getPointer(),
                    self::CODE_COMMENT_FORBIDDEN
                );
            }
        }

        // Check is done, comment no longer needed..
        $this->lastComments = null;
    }

    /**
     * @param string $type
     * @param string $comment
     * @param string $targetName
     * @return bool
     */
    private function compare($type, $comment, $targetName)
    {
        // Remove any non-alphanumeric characters (including spaces)
        $comment = preg_replace('|[^a-z]|i', '', $comment);
        $targetName = preg_replace('|[^a-z]|i', '', $targetName);

        if (strcasecmp($comment, $targetName) === 0) {
            return true;
        }

        if (strcasecmp($comment, $type . $targetName) === 0) {
            return true;
        }

        if (strcasecmp($comment, $targetName . $type) === 0) {
            return true;
        }

        return false;
    }
}
