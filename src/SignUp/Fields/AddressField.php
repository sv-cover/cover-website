<?php

namespace App\SignUp\Fields;

use App\DataIter\DataIterMember;
use App\SignUp\SignUpFieldInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

class AddressField implements SignUpFieldInterface
{
    public $name;

    public $required;

    public $autofill;

    public static function getTypeLabel(): string
    {
        return __('Address field');
    }

    public function getConfiguration(): array
    {
        return [
            'required' => (bool) $this->required,
            'autofill' => (bool) $this->autofill,
        ];
    }

    public function setConfiguration(array $configuration): void
    {
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
        return \json_encode([
            'address' => $form->get($this->name . '-address')->getData(),
            'city' => $form->get($this->name . '-city')->getData(),
        ]);
    }

    public function prefill(DataIterMember $member): ?string
    {
        if (!$this->autofill)
            return null;

        return \json_encode([
            'address' => $member['adres'],
            'city' => $member['woonplaats']
        ]);
    }

    public function buildForm(FormBuilderInterface $builder): void
    {
        $builder
            ->add($this->name . '-address', TextType::class, [
                'label' => __('Address'),
                'required' => $this->required,
                'constraints' => $this->required ? new Assert\NotBlank() : [],
                'attr' => [
                    'placeholder' => __('E.g. Nijenborgh 9'),
                ],
                'help' => __('Street name + number'),
            ])
            ->add($this->name . '-city', TextType::class, [
                'label' => __('Place of residence'),
                'required' => $this->required,
                'constraints' => $this->required ? new Assert\NotBlank() : [],
                'attr' => [
                    'placeholder' => __('E.g. Groningen'),
                ],
            ]);
    }

    public function buildConfigurationForm(FormBuilderInterface $builder): void
    {
        $builder
            ->add('required', CheckboxType::class, [
                'label' => __('Filling in address is mandatory.'),
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
        return [
            $this->name . '-address' => 'Address',
            $this->name . '-city' => 'Place of residence'
        ];
    }

    public function getFormData($value): array
    {
        return $this->export($value);
    }

    public function export($value): array
    {
        $data = $value !== null ? \json_decode($value, true) : [];
        return [
            $this->name . '-address' => $data['address'] ?? '',
            $this->name . '-city' => $data['city'] ?? ''
        ];
    }
}
