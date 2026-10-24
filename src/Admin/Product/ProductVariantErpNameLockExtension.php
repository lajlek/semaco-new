<?php

declare(strict_types=1);

namespace App\Admin\Product;

use App\Entity\Product\ProductVariant;
use Sylius\Bundle\ProductBundle\Form\Type\ProductVariantType;
use Sylius\Component\Product\Model\ProductVariantTranslationInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

final class ProductVariantErpNameLockExtension extends AbstractTypeExtension
{
    /** @var array<int, string> */
    private array $names = [];

    public static function getExtendedTypes(): iterable
    {
        return [ProductVariantType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event): void {
            $variant = $event->getData();
            if (!$variant instanceof ProductVariant || $variant->getExternalIdentifier() === null) {
                return;
            }

            $event->getForm()->add('erpNameLocked', CheckboxType::class, [
                'label' => 'app.admin.product_variant.erp_name_lock.label',
                'help' => 'app.admin.product_variant.erp_name_lock.help',
                'required' => false,
            ]);
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $variant = $event->getForm()->getData();
            if ($variant instanceof ProductVariant && $variant->getExternalIdentifier() !== null) {
                $this->names[spl_object_id($variant)] = self::names($variant);
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $variant = $event->getData();
            if (!$variant instanceof ProductVariant || !isset($this->names[spl_object_id($variant)])) {
                return;
            }

            $before = $this->names[spl_object_id($variant)];
            unset($this->names[spl_object_id($variant)]);
            if ($before !== self::names($variant)) {
                $variant->setErpNameLocked(true);
            }
        }, -100);
    }

    private static function names(ProductVariant $variant): string
    {
        $names = [];
        foreach ($variant->getTranslations() as $translation) {
            $name = $translation instanceof ProductVariantTranslationInterface ? trim((string) $translation->getName()) : '';
            if ($name !== '') {
                $names[(string) $translation->getLocale()] = $name;
            }
        }
        ksort($names);

        return (string) json_encode($names);
    }
}
