<?php

namespace App\Form;

use App\SignUp\SignUpFormManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;
use App\Form\DataTransformer\StringToDateTimeTransformer;


class SignUpFieldType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $typeChoices = \array_flip(\array_map(
            fn($type): string => $type::getTypeLabel(),
            SignUpFormManager::getSubscribedServices(),
        ));

        $builder
            ->add('field_type', ChoiceType::class, [
                'choices' => $typeChoices,
            ])
            ->add('submit', SubmitType::class, ['label' => 'Add field'])
        ;
    }
}
