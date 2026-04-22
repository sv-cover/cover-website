<?php
namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

class ProfilePictureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('photo', FileType::class, [
                'label' => __('Photo'),
                'cta' => __('Choose photo…'),
                'constraints' => [
                    new Assert\Image([
                        'maxSize' => ini_get('upload_max_filesize'),
                        'maxSizeMessage' => 'The file is too large. Please try a file smaller than {{ limit }} {{ suffix }}.',
                        'mimeTypes' => [
                            'image/jpeg',
                        ],
                        'mimeTypesMessage' => __('Please upload a valid JPEG-image.'),
                        'sizeNotDetectedMessage' => __('The uploaded file doesn’t appear to be an image.'),
                    ])
                ],
                'attr' => [
                    'accept' => 'image/jpeg',
                    'capture' => 'user',
                    'data-max-filesize' => ini_get('upload_max_filesize'),
                ],
            ])
            ->add('submit', SubmitType::class)
        ;
    }
}
