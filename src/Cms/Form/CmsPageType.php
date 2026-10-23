<?php

declare(strict_types=1);

namespace App\Cms\Form;

use App\Cms\Entity\CmsPage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

final class CmsPageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Název',
                'constraints' => [new NotBlank(), new Length(max: 255)],
            ])
            ->add('slug', TextType::class, [
                'label' => 'Adresa (slug)',
                'help' => 'Stránka bude na /cs_CZ/page/{slug}',
                'constraints' => [new NotBlank(), new Length(max: 128), new Regex(pattern: '/^[a-z0-9-]+$/', message: 'Jen malá písmena, čísla a pomlčky.')],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Obsah (HTML)',
                'required' => false,
                'attr' => ['rows' => 20],
            ])
            ->add('metaTitle', TextType::class, ['label' => 'Titulek pro vyhledávače', 'required' => false])
            ->add('metaDescription', TextareaType::class, [
                'label' => 'Popis pro vyhledávače',
                'required' => false,
                'attr' => ['rows' => 2, 'maxlength' => 500],
                'constraints' => [new Length(max: 500)],
            ])
            ->add('position', IntegerType::class, ['label' => 'Pořadí v menu Informace'])
            ->add('enabled', CheckboxType::class, ['label' => 'Zobrazit na webu', 'required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CmsPage::class]);
    }
}
