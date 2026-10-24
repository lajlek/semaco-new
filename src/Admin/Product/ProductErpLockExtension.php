<?php

declare(strict_types=1);

namespace App\Admin\Product;

use App\Entity\Product\Product;
use Sylius\Bundle\ProductBundle\Form\Type\ProductType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

final class ProductErpLockExtension extends AbstractTypeExtension
{
    private const LABELS = [
        Product::ERP_FIELD_NAME => 'app.admin.product.erp_lock.name',
        Product::ERP_FIELD_DESCRIPTION => 'app.admin.product.erp_lock.description',
        Product::ERP_FIELD_SHORT_DESCRIPTION => 'app.admin.product.erp_lock.short_description',
        Product::ERP_FIELD_META => 'app.admin.product.erp_lock.meta',
        Product::ERP_FIELD_TAXONS => 'app.admin.product.erp_lock.taxons',
        Product::ERP_FIELD_ATTRIBUTES => 'app.admin.product.erp_lock.attributes',
    ];

    /** @var array<int, array<string, string>> */
    private array $fingerprints = [];

    public static function getExtendedTypes(): iterable
    {
        return [ProductType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event): void {
            $product = $event->getData();
            if (!$product instanceof Product || $product->getExternalIdentifier() === null) {
                return;
            }

            $event->getForm()->add('erpLockedFields', ChoiceType::class, [
                'label' => 'app.admin.product.erp_lock.label',
                'help' => 'app.admin.product.erp_lock.help',
                'choices' => array_flip(self::LABELS),
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ]);
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $product = $event->getForm()->getData();
            if ($product instanceof Product && $product->getExternalIdentifier() !== null) {
                $this->fingerprints[spl_object_id($product)] = ProductContentFingerprint::of($product);
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $product = $event->getData();
            if (!$product instanceof Product || !isset($this->fingerprints[spl_object_id($product)])) {
                return;
            }

            $before = $this->fingerprints[spl_object_id($product)];
            unset($this->fingerprints[spl_object_id($product)]);
            $changed = ProductContentFingerprint::changedFields($before, ProductContentFingerprint::of($product));
            if ($changed !== []) {
                $product->setErpLockedFields([...$product->getErpLockedFields(), ...$changed]);
            }
        }, -100);
    }
}
