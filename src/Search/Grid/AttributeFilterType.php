<?php

declare(strict_types=1);

namespace App\Search\Grid;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class AttributeFilterType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['allow_extra_fields' => true, 'compound' => true]);
    }
}
