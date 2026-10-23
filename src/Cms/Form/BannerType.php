<?php

declare(strict_types=1);

namespace App\Cms\Form;

use App\Cms\Entity\Banner;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

final class BannerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'Kód (homepage = úvodní stránka)',
                'constraints' => [new NotBlank(), new Length(max: 64), new Regex(pattern: '/^[a-z0-9_-]+$/', message: 'Jen malá písmena, čísla, pomlčky a podtržítka.')],
            ])
            ->add('file', FileType::class, [
                'label' => 'Obrázek',
                'required' => false,
                'help' => 'JPG, PNG nebo WebP, doporučená šířka alespoň 1920 px. Nový soubor nahradí původní.',
                'constraints' => [new Image(maxSize: '8M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp'])],
            ])
            ->add('alt', TextType::class, [
                'label' => 'Popis obrázku (pro čtečky)',
                'required' => false,
                'constraints' => [new Length(max: 255)],
            ])
            ->add('link', UrlType::class, [
                'label' => 'Odkaz po kliknutí',
                'required' => false,
                'default_protocol' => null,
                'constraints' => [new Length(max: 500)],
            ])
            ->add('enabled', CheckboxType::class, ['label' => 'Zobrazit na webu', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Banner::class]);
    }

    public function getBlockPrefix(): string
    {
        return 'app_cms_banner';
    }
}
