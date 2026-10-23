<?php

declare(strict_types=1);

namespace App\Checkout\Terms;

use Sylius\Bundle\CoreBundle\Form\Type\Checkout\CompleteType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\IsTrue;

final class CheckoutTermsExtension extends AbstractTypeExtension
{
    private const GROUPS = ['sylius', 'sylius_checkout_complete'];

    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public static function getExtendedTypes(): iterable
    {
        return [CompleteType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('acceptTerms', CheckboxType::class, [
                'mapped' => false,
                'required' => true,
                'label' => 'app.checkout.accept_terms',
                'label_html' => true,
                'label_translation_parameters' => ['%url%' => $this->pageUrl('obchodni-podminky')],
                'translation_domain' => 'checkout',
                'constraints' => [new IsTrue(message: 'app.checkout.accept_terms_required', groups: self::GROUPS)],
            ])
            ->add('acceptPrivacy', CheckboxType::class, [
                'mapped' => false,
                'required' => true,
                'label' => 'app.checkout.accept_privacy',
                'label_html' => true,
                'label_translation_parameters' => ['%url%' => $this->pageUrl('zasady-ochrany-osobnich-udaju')],
                'translation_domain' => 'checkout',
                'constraints' => [new IsTrue(message: 'app.checkout.accept_privacy_required', groups: self::GROUPS)],
            ]);
    }

    private function pageUrl(string $slug): string
    {
        return $this->urlGenerator->generate('app_shop_cms_page_show', ['slug' => $slug]);
    }
}
