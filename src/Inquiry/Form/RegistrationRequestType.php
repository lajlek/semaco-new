<?php

declare(strict_types=1);

namespace App\Inquiry\Form;

use App\Inquiry\Entity\RegistrationRequest;
use App\Inquiry\Security\SubmissionGuard;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class RegistrationRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('companyName', TextType::class, ['label' => 'app.inquiry.field.company_name', 'attr' => ['autocomplete' => 'organization']])
            ->add('identificationNumber', TextType::class, ['label' => 'app.inquiry.field.identification_number'])
            ->add('street', TextType::class, ['label' => 'app.inquiry.field.street', 'attr' => ['autocomplete' => 'address-line1']])
            ->add('streetNumber', TextType::class, ['label' => 'app.inquiry.field.street_number'])
            ->add('city', TextType::class, ['label' => 'app.inquiry.field.city', 'attr' => ['autocomplete' => 'address-level2']])
            ->add('postcode', TextType::class, ['label' => 'app.inquiry.field.postcode', 'attr' => ['autocomplete' => 'postal-code']])
            ->add('email', EmailType::class, ['label' => 'app.inquiry.field.email', 'attr' => ['autocomplete' => 'email']])
            ->add('phone', TelType::class, ['label' => 'app.inquiry.field.phone', 'required' => false, 'attr' => ['autocomplete' => 'tel']])
            ->add('firstName', TextType::class, ['label' => 'app.inquiry.field.first_name', 'required' => false, 'attr' => ['autocomplete' => 'given-name']])
            ->add('lastName', TextType::class, ['label' => 'app.inquiry.field.last_name', 'required' => false, 'attr' => ['autocomplete' => 'family-name']])
            ->add('message', TextareaType::class, ['label' => 'app.inquiry.field.note', 'required' => false, 'attr' => ['rows' => 5]])
            ->add(SubmissionGuard::HONEYPOT_FIELD, HoneypotType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RegistrationRequest::class,
            'translation_domain' => 'inquiry',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'app_registration_request';
    }
}
