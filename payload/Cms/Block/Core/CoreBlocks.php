<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Block\Core;

use App\com_pinoox_cms\Cms\Block\BlockAttributeDefinition;
use App\com_pinoox_cms\Cms\Block\BlockAttributeType;
use App\com_pinoox_cms\Cms\Block\BlockDefinition;
use App\com_pinoox_cms\Cms\Block\BlockRegistry;

final class CoreBlocks
{
    public const OWNER = 'cms.core';

    public static function register(BlockRegistry $registry): void
    {
        foreach ([
            new BlockDefinition(
                'block:core/section',
                self::OWNER,
                'core/section',
                'Section',
                'layout',
                'layout-template',
                1,
                '1.0.0',
                [
                    'tag' => new BlockAttributeDefinition(
                        'tag',
                        BlockAttributeType::String,
                        default: 'section',
                        rules: ['enum' => ['section', 'div', 'main', 'article', 'aside', 'header', 'footer']]
                    ),
                    'className' => new BlockAttributeDefinition(
                        'className',
                        BlockAttributeType::String,
                        default: '',
                        rules: ['maxLength' => 255],
                    ),
                ],
                [
                    'anchor' => true,
                    'spacing' => true,
                    'dimensions' => true,
                    'responsive' => true,
                    'className' => true,
                    'reusable' => true,
                ],
                true,
                ['*'],
            ),
            new BlockDefinition(
                'block:core/heading',
                self::OWNER,
                'core/heading',
                'Heading',
                'text',
                'heading',
                2,
                '1.1.0',
                [
                    'text' => new BlockAttributeDefinition(
                        'text',
                        BlockAttributeType::String,
                        required: true,
                        rules: ['maxLength' => 2000],
                    ),
                    'level' => new BlockAttributeDefinition(
                        'level',
                        BlockAttributeType::Integer,
                        default: 2,
                        rules: ['min' => 1, 'max' => 6],
                    ),
                ],
                [
                    'align' => true,
                    'color' => true,
                    'typography' => true,
                    'spacing' => true,
                    'responsive' => true,
                ],
                false,
                [],
                migrations: [
                    ['from' => 1, 'to' => 2, 'class' => 'CoreHeadingV1ToV2'],
                ],
            ),
            new BlockDefinition(
                'block:core/paragraph',
                self::OWNER,
                'core/paragraph',
                'Paragraph',
                'text',
                'text',
                1,
                '1.0.0',
                [
                    'text' => new BlockAttributeDefinition(
                        'text',
                        BlockAttributeType::String,
                        required: true,
                        rules: ['maxLength' => 20000],
                    ),
                ],
                [
                    'align' => true,
                    'color' => true,
                    'typography' => true,
                    'spacing' => true,
                    'responsive' => true,
                ],
            ),
            new BlockDefinition(
                'block:core/global-reference',
                self::OWNER,
                'core/global-reference',
                'Global Block',
                'reusable',
                'component',
                1,
                '1.0.0',
                [
                    'globalId' => new BlockAttributeDefinition(
                        'globalId',
                        BlockAttributeType::Integer,
                        required: true,
                        rules: ['min' => 1],
                    ),
                ],
                [
                    'reusable' => true,
                ],
                false,
                [],
            ),
            new BlockDefinition(
                'block:core/button',
                self::OWNER,
                'core/button',
                'Button',
                'interactive',
                'mouse-pointer-click',
                1,
                '1.0.0',
                [
                    'label' => new BlockAttributeDefinition(
                        'label',
                        BlockAttributeType::String,
                        required: true,
                        rules: ['maxLength' => 500],
                    ),
                    'url' => new BlockAttributeDefinition(
                        'url',
                        BlockAttributeType::Url,
                        required: true,
                        rules: ['maxLength' => 2000],
                    ),
                    'newTab' => new BlockAttributeDefinition(
                        'newTab',
                        BlockAttributeType::Boolean,
                        default: false,
                    ),
                ],
                [
                    'color' => true,
                    'typography' => true,
                    'spacing' => true,
                    'responsive' => true,
                ],
            ),
        ] as $block) {
            $registry->register($block);
        }
    }
}
