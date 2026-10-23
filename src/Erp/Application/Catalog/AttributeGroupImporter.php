<?php

declare(strict_types=1);

namespace App\Erp\Application\Catalog;

use App\Entity\Product\ProductAttribute;
use App\Erp\Application\ImportResult;
use App\Erp\Port\Catalog\AttributeGroupSyncInterface;
use App\Erp\Port\Catalog\Dto\AttributeGroupSnapshot;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Attribute\AttributeType\IntegerAttributeType;
use Sylius\Component\Attribute\AttributeType\SelectAttributeType;
use Sylius\Component\Product\Model\ProductOptionInterface;
use Sylius\Component\Product\Model\ProductOptionValueInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;

final readonly class AttributeGroupImporter
{
    /**
     * @param RepositoryInterface<ProductAttribute> $attributeRepository
     * @param FactoryInterface<ProductAttribute> $attributeFactory
     * @param RepositoryInterface<ProductOptionInterface> $optionRepository
     * @param FactoryInterface<ProductOptionInterface> $optionFactory
     * @param FactoryInterface<ProductOptionValueInterface> $optionValueFactory
     */
    public function __construct(
        private AttributeGroupSyncInterface $attributeGroupSync,
        private RepositoryInterface $attributeRepository,
        private FactoryInterface $attributeFactory,
        private RepositoryInterface $optionRepository,
        private FactoryInterface $optionFactory,
        private FactoryInterface $optionValueFactory,
        private EntityManagerInterface $entityManager,
        private string $localeCode,
    ) {
    }

    public function import(): ImportResult
    {
        $result = new ImportResult('attributes');

        foreach ($this->attributeGroupSync->pullAll() as $snapshot) {
            $isNew = $this->upsertAttribute($snapshot);
            $this->upsertOption($snapshot);
            $isNew ? $result->created() : $result->updated();
            $this->entityManager->flush();
        }

        return $result;
    }

    private function upsertAttribute(AttributeGroupSnapshot $snapshot): bool
    {
        $code = CatalogCodeGenerator::attributeCode($snapshot->externalIdentifier);
        $attribute = $this->attributeRepository->findOneBy(['code' => $code]);
        $isNew = !$attribute instanceof ProductAttribute;
        if ($isNew) {
            $attribute = $this->attributeFactory->createNew();
            $attribute->setCode($code);
            $attribute->setTranslatable(false);
            if ($snapshot->type === AttributeGroupSnapshot::TYPE_INTEGER) {
                $attribute->setType(IntegerAttributeType::TYPE);
                $attribute->setStorageType('integer');
            } else {
                $attribute->setType(SelectAttributeType::TYPE);
                $attribute->setStorageType('json');
            }
            $this->entityManager->persist($attribute);
        }

        $attribute->setCurrentLocale($this->localeCode);
        $attribute->setFallbackLocale($this->localeCode);
        $attribute->setName($snapshot->name);
        $attribute->setPosition($snapshot->position);
        $attribute->setFilterType($snapshot->filterType);

        if ($attribute->getType() === SelectAttributeType::TYPE) {
            $configuration = $attribute->getConfiguration();
            $choices = is_array($configuration['choices'] ?? null) ? $configuration['choices'] : [];
            foreach ($snapshot->values as $value) {
                $choices[CatalogCodeGenerator::attributeChoiceKey($value->key)] = [$this->localeCode => $value->value];
            }
            $attribute->setConfiguration([
                'choices' => $choices,
                'multiple' => true,
                'min' => null,
                'max' => null,
            ]);
        }

        return $isNew;
    }

    private function upsertOption(AttributeGroupSnapshot $snapshot): void
    {
        $code = CatalogCodeGenerator::optionCode($snapshot->externalIdentifier);
        $option = $this->optionRepository->findOneBy(['code' => $code]);
        if (!$option instanceof ProductOptionInterface) {
            $option = $this->optionFactory->createNew();
            $option->setCode($code);
            $this->entityManager->persist($option);
        }

        $option->setCurrentLocale($this->localeCode);
        $option->setFallbackLocale($this->localeCode);
        $option->setName($snapshot->name);
        $option->setPosition($snapshot->position);

        $existing = [];
        foreach ($option->getValues() as $value) {
            $existing[(string) $value->getCode()] = $value;
        }

        foreach ($snapshot->values as $value) {
            $valueCode = CatalogCodeGenerator::optionValueCode($snapshot->externalIdentifier, $value->key);
            $optionValue = $existing[$valueCode] ?? null;
            if (!$optionValue instanceof ProductOptionValueInterface) {
                $optionValue = $this->optionValueFactory->createNew();
                $optionValue->setCode($valueCode);
                $option->addValue($optionValue);
                $existing[$valueCode] = $optionValue;
            }

            $optionValue->setCurrentLocale($this->localeCode);
            $optionValue->setFallbackLocale($this->localeCode);
            $optionValue->setValue($value->value);
        }
    }
}
