<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Block\Document;

use App\com_pinoox_cms\Cms\Block\BlockAttributeDefinition;
use App\com_pinoox_cms\Cms\Block\BlockAttributeType;

final class BlockAttributeValidator
{
    public function valid(BlockAttributeDefinition $definition, mixed $value): bool
    {
        if (!$this->matchesType($definition->type, $value)) {
            return false;
        }

        $rules = $definition->rules;

        if (is_string($value)) {
            $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
            if (isset($rules['minLength']) && $length < (int)$rules['minLength']) return false;
            if (isset($rules['maxLength']) && $length > (int)$rules['maxLength']) return false;
            if (isset($rules['pattern']) && preg_match((string)$rules['pattern'], $value) !== 1) return false;
            if (isset($rules['enum']) && is_array($rules['enum']) && !in_array($value, $rules['enum'], true)) return false;
        }

        if (is_int($value) || is_float($value)) {
            if (isset($rules['min']) && $value < $rules['min']) return false;
            if (isset($rules['max']) && $value > $rules['max']) return false;
        }

        if (is_array($value)) {
            if (isset($rules['maxItems']) && count($value) > (int)$rules['maxItems']) return false;
        }

        if ($definition->type === BlockAttributeType::Url) {
            $url = trim((string)$value);
            if ($url === '' || strlen($url) > 2000) return false;
            $parts = parse_url($url);
            if ($parts === false) return false;
            $scheme = strtolower((string)($parts['scheme'] ?? ''));
            if ($scheme !== '' && !in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)) return false;
            if ($scheme === '' && !str_starts_with($url, '/') && !str_starts_with($url, '#')) return false;
        }

        if ($definition->type === BlockAttributeType::Media && (int)$value < 1) {
            return false;
        }

        return true;
    }

    private function matchesType(BlockAttributeType $type, mixed $value): bool
    {
        return match ($type) {
            BlockAttributeType::String,
            BlockAttributeType::RichText,
            BlockAttributeType::Url => is_string($value),
            BlockAttributeType::Integer,
            BlockAttributeType::Media => is_int($value),
            BlockAttributeType::Number => is_int($value) || is_float($value),
            BlockAttributeType::Boolean => is_bool($value),
            BlockAttributeType::Array => is_array($value) && array_is_list($value),
            BlockAttributeType::Object => is_array($value) && !array_is_list($value),
        };
    }
}
