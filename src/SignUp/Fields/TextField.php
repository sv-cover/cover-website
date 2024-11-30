<?php

namespace App\SignUp\Fields;

use App\SignUp\SignUpFieldInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextAreaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

class TextField implements SignUpFieldInterface
{
    public $name;

    public $label;

    public $required;

    public $multiline;

    public static function getTypeLabel(): string
    {
        return __('Text field');
    }

    public function getConfiguration(): array
    {
        return [
            'label' => $this->label,
            'required' => (bool) $this->required,
            'multiline' => (bool) $this->multiline
        ];
    }

    public function setConfiguration(array $configuration): void
    {
        $this->label = $configuration['label'] ?? '';
        $this->required = $configuration['required'] ?? false;
        $this->multiline = $configuration['multiline'] ?? false;
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
        return $form->get($this->name)->getData();
    }

    public function prefill(\DataIterMember $member): ?string
    {
        return null;
    }

    public function buildForm(FormBuilderInterface $builder): void
    {
        if ($this->multiline)
            $builder
                ->add($this->name, TextAreaType::class, [
                    'label' => $this->label,
                    'required' => $this->required,
                    'constraints' => $this->required ? new Assert\NotBlank() : [],
                ]);
        else
            $builder
                ->add($this->name, TextType::class, [
                    'label' => $this->label,
                    'required' => $this->required,
                    'constraints' => $this->required ? new Assert\NotBlank() : [],
                ]);
    }

    public function buildConfigurationForm(FormBuilderInterface $builder): void
    {
        $builder
            ->add('label', TextType::class, [
                'label' => __('Label for text field'),
                'constraints' => new Assert\NotBlank(),
            ])
            ->add('multiline', ChoiceType::class, [
                'label' => false,
                'expanded' => true,
                'multiple' => false,
                'choices' => [
                    __('Single line of text') => false,
                    __('Multiple lines of text') => true,
                ],
            ])
            ->add('required', CheckboxType::class, [
                'label' => __('Filling in this field is mandatory.'),
                'required' => false,
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
