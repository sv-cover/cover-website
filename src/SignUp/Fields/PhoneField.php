<?php

namespace App\SignUp\Fields;

use App\DataIter\DataIterMember;
use App\SignUp\SignUpFieldInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

class PhoneField implements SignUpFieldInterface
{
    public $name;

    public $label;

    public $required;

    public $autofill;

    public static function getTypeLabel(): string
    {
        return __('Phone number field');
    }

    public function getConfiguration(): array
    {
        return [
            'label' => $this->label,
            'required' => (bool) $this->required,
            'autofill' => (bool) $this->autofill
        ];
    }

    public function setConfiguration(array $configuration): void
    {
        $this->label = $configuration['label'] ?? 'Phone';
        $this->required = $configuration['required'] ?? false;
        $this->autofill = $configuration['autofill'] ?? true;
    }

    public function getConfigurationTemplate(): string
    {
        return 'sign_ups/configuration/_field.html.twig';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function process(FormInterface $form): ?string
    {
        $value = $form->get($this->name)->getData();
        // A phone number doesn't need to contain spaces
        $value = \str_replace(' ', '', $value);
        return $value;
    }

    public function prefill(DataIterMember $member): ?string
    {
        if (!$this->autofill)
            return null;

        return $member['telefoonnummer'];
    }

    public function buildForm(FormBuilderInterface $builder): void
    {
        $builder
            ->add($this->name, TelType::class, [
                'label' => __('Phone number'),
                'required' => $this->required,
                'constraints' => $this->required ? new Assert\NotBlank() : [],
                'attr' => [
                    'placeholder' => __('E.g. +31 6 12345678'),
                ],
            ]);
    }

    public function buildConfigurationForm(FormBuilderInterface $builder): void
    {
        $builder
            ->add('required', CheckboxType::class, [
                'label' => __('Filling in phone number is mandatory.'),
                'required' => false,
            ])
            ->add('autofill', CheckboxType::class, [
                'label' => __('Autofill this field with member data.'),
                'required' => false,
                'help' => __('Disable if people are not supposed to fill in their own information.'),
            ])
            ->add('submit', SubmitType::class, [
                'label' => __('Modify field'),
            ]);
    }

    public function columnLabels(): array
    {
        return [$this->name => $this->label];
    }

    public function getFormData($value): array
    {
        return $this->export($value);
    }

    public function export($value): array
    {
        return [$this->name => $value];
    }
}
