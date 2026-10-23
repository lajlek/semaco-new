<?php

declare(strict_types=1);

namespace App\Inquiry\Form;

use App\Inquiry\Entity\ContactMessage;
use App\Inquiry\Security\SubmissionGuard;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ContactMessageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, ['label' => 'app.inquiry.field.first_name', 'required' => false, 'attr' => ['autocomplete' => 'given-name']])
            ->add('lastName', TextType::class, ['label' => 'app.inquiry.field.last_name', 'required' => false, 'attr' => ['autocomplete' => 'family-name']])
            ->add('email', EmailType::class, ['label' => 'app.inquiry.field.email', 'attr' => ['autocomplete' => 'email']])
            ->add('phone', TelType::class, ['label' => 'app.inquiry.field.phone', 'required' => false, 'attr' => ['autocomplete' => 'tel']])
            ->add('message', TextareaType::class, ['label' => 'app.inquiry.field.message', 'attr' => ['rows' => 6]])
            ->add(SubmissionGuard::HONEYPOT_FIELD, HoneypotType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContactMessage::class,
            'translation_domain' => 'inquiry',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'app_contact_message';
    }
}
