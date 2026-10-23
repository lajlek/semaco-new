<?php

declare(strict_types=1);

namespace App\Inquiry\Form;

use App\Inquiry\Entity\ContactMessageAnswer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ContactMessageAnswerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('message', TextareaType::class, ['label' => 'app.inquiry.field.answer', 'attr' => ['rows' => 5]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContactMessageAnswer::class,
            'translation_domain' => 'inquiry',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'app_contact_message_answer';
    }
}
