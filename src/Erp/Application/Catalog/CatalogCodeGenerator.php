<?php

declare(strict_types=1);

namespace App\Erp\Application\Catalog;

use Behat\Transliterator\Transliterator;

final class CatalogCodeGenerator
{
    public static function taxonCode(string $name): string
    {
        $code = Transliterator::transliterate($name, '_');

        return $code !== '' ? $code : 'taxon';
    }

    public static function slugFromCode(string $code): string
    {
        return str_replace('_', '-', $code);
    }

    public static function attributeCode(string $groupExternalIdentifier): string
    {
        return 'attr_group_' . $groupExternalIdentifier;
    }

    public static function attributeChoiceKey(string $valueKey): string
    {
        return 'attribute_' . $valueKey;
    }

    public static function optionCode(string $groupExternalIdentifier): string
    {
        return 'option_group_' . $groupExternalIdentifier;
    }

    public static function optionValueCode(string $groupExternalIdentifier, string $valueKey): string
    {
        return $groupExternalIdentifier . '_option_' . $valueKey;
    }
}
