<?php

/**
 * Configuration options for PHP CS Fixer. Formatting rules should stay in line with
 * current PHP formatting style rules defined in plotbox-codestyle repo. Below rules
 * should help to automatically format code when possible, leaving only a smaller
 * number of fixes (that need to be reviewed manually). Configurator link below has
 * in depth examples of before/after for each type.
 *
 * @see https://github.com/FriendsOfPHP/PHP-CS-Fixer
 * @see https://mlocati.github.io/php-cs-fixer-configurator
 */

$autoloaderCandidates = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../../../autoload.php',
];

foreach ($autoloaderCandidates as $autoloader) {
    if (file_exists($autoloader)) {
        require_once $autoloader;
        break;
    }
}

use PhpCsFixer\Config;
use PhpCsFixerCustomFixers\Fixers as CustomFixers;
use PhpCsFixerCustomFixers\Fixer as CustomFixer;

$config = new Config();
$config->registerCustomFixers(new CustomFixers());
$config->setCacheFile(sys_get_temp_dir() . '/.php-cs-fixer.cache');
return $config->setRules(
        [
            // ####################
            // #### Base Sets #####
            // ####################

            '@PSR1' => true,
            '@PSR2' => true,
            '@Symfony' => true,

            // ####################
            // #### Overwrites ####
            // ####################

            // (Differ from Symfony) Prefer single quotes where possible
            'single_quote' => true,

            // (Differ from Symfony) We prefer not to have trailing commas in arrays
            'trailing_comma_in_multiline' => false,

            // (Differ from Symfony) No enforcing of blank line (e.g., before return statement)
            'blank_line_before_statement' => false,

            // (Differ from Symfony) Prefer single space with concatenations for clarity
            'concat_space' => [
                'spacing' => 'one'
            ],

            // (Differ from Symfony) We allow for the 'property-read' alias (as it provides some helpful
            // intent to developers); Hence it is omitted from the default list below. 'property-write' is
            // more or less useless so we still convert it to just 'property'
            'phpdoc_no_alias_tag' => [
                'replacements' => [
                    'property-write' => 'property',
                    'type' => 'var',
                    'link' => 'see'
                ]
            ],

            // (Differ from Symfony) Disable this as it randomly changes phpdoc descriptions to start with lowercase
            'phpdoc_annotation_without_dot' => false,

            // (Differ from Symfony) Disable this because sometimes is useful to add @var annotations
            'phpdoc_to_comment' => false,

            // (Differ from Symfony) It is more correct (according to phpDoc) to
            // use @inheritDoc (non-inlined) when no other info exists in the phpDoc
            // @see https://docs.phpdoc.org/guides/inheritance.html.
            'phpdoc_tag_type' => [
                'tags' => ['inheritdoc' => 'annotation']
            ],

            // (Differ from Symfony) Allow custom line spacing in phpDoc blocks
            'phpdoc_separation' => false,

            // (Differ from Symfony) Who cares if a docBlock ends in punctuation?
            'phpdoc_summary' => false,

            // (Differ from Symfony) This causes unneccesary diff/merge issues on fixing files..
            'ordered_imports' => false,

            // (Differ from Symfony) Symfony have a weird default for this which removes the '?'
            // (nullable union shorthand) from prepend for object types. This is likely some sort
            // of legacy/consistency thing as it is obviously better to be more explicit about
            // nullability than rely on legacy implicit PHP type behaviours
            // @see https://mlocati.github.io/php-cs-fixer-configurator/#version:3.25|fixer:nullable_type_declaration_for_default_null_value
            'nullable_type_declaration_for_default_null_value' => true,

            // (Differ from Symfony) Sometimes it just makes formatting sense to use multiple lines..
            'single_line_throw' => false,

            // (Differ from Symfony) Prefer left aligned phpdoc. This minimises unnecessary whitespace.
            // IDEs will have highlighting for docblocks so they are still perfectly clear
            'phpdoc_align' => [
                'align' => 'left'
            ],

            // (Differ from Symfony) Allow non-fqcn classes in PhpUnit phpdoc
            'php_unit_fqcn_annotation' => false,

            // (Differ from Symfony) Post increment (i++) is clearer
            'increment_style' => [
                'style' => 'post'
            ],

            // (Differ from Symfony) Prefer normal (anti-yoda) style
            'yoda_style' => [
                'always_move_variable' => false,
                'equal' => false,
                'identical' => false,
                'less_and_greater' => false
            ],

            // (Differ from Symfony) Prefer short echo tag syntax when templating
            'echo_tag_syntax' => [
                'format' => 'short',
                'shorten_simple_statements_only' => true
            ],

            // (Differ from Symfony) We prefer snake case on tests for readability
            'php_unit_method_casing' => [
                'case' => 'snake_case'
            ],

            // (Differ from Symfony) We prefer to keep @inheritDoc as clear marker that file inherits/implements
            'no_superfluous_phpdoc_tags' => [
                'remove_inheritdoc' => false
            ],

            // ######################################
            // #### Extras (PhpCsFixer or other) ####
            // ######################################

            'multiline_whitespace_before_semicolons' => true,
            'combine_consecutive_unsets' => true,
            'backtick_to_shell_exec' => true,
            'array_indentation' => true,
            'phpdoc_no_empty_return' => true,
            'phpdoc_order' => true,
            'general_phpdoc_annotation_remove' => [
                'annotations' => [
                    'author'
                ]
            ],
            'global_namespace_import' => [
                'import_classes' => true,
                'import_constants' => true,
                'import_functions' => true
            ],
            'linebreak_after_opening_tag' => true,
            'phpdoc_line_span' => [
                'const' => 'single',
                'method' => 'single',
                'property' => 'single'
            ],
            'self_static_accessor' => true,

            CustomFixer\MultilinePromotedPropertiesFixer::name() => true
        ]
    )
    ->setLineEnding("\n");
