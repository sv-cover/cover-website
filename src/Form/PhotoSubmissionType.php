<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

class PhotoSubmissionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('photo', FileType::class, [
                'label' => __('Photos'),
                'multiple' => true,
                'attr' => ['accept' => 'image/jpeg,image/png,image/gif'],
                'constraints' => [
                    new Assert\NotNull(['message' => __('Please select at least one photo to upload.')]),
                    new Assert\All([
                        'constraints' => [
                            new Assert\Image([
                                'maxSize' => '20M',
                                'mimeTypes' => ['image/jpeg', 'image/png', 'image/gif'],
                                'mimeTypesMessage' => __('Please upload a valid image (JPEG, PNG, or GIF).'),
                            ]),
                        ],
                    ]),
                ],
            ])
            ->add('beschrijving', TextareaType::class, [
                'label' => __('Description'),
                'required' => false,
            ])
            ->add('consent', CheckboxType::class, [
                'label' => __('I confirm that every recognizable person in these photos consents to their photo being published on this website.'),
                'required' => true,
                'mapped' => false,
                'constraints' => [
                    new Assert\IsTrue(['message' => __('You must confirm consent before submitting photos.')]),
                ],
            ])
            ->add('submit', SubmitType::class, ['label' => __('Submit photos')])
        ;
    }
}
