<?php

namespace App\SignUp\Fields;

use App\DataIter\DataIterMember;
use App\SignUp\SignUpFieldInterface;
use App\Form\DataTransformer\IntToBooleanTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

class CheckboxField implements SignUpFieldInterface
{
    public $name;

    public $description;

    public $required;

    public static function getTypeLabel(): string
    {
        return __('Checkbox');
    }

    public function getConfiguration(): array
    {
        return [
            'required' => $this->required,
            'description' => $this->description,
        ];
    }

    public function setConfiguration(array $configuration): void
    {
        $this->required = $configuration['required'] ?? false;
        $this->description = $configuration['description'] ?? '';
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

    public function prefill(DataIterMember $member): ?string
    {
        return null;
    }

    public function buildForm(FormBuilderInterface $builder): void
    {
        $builder
            ->add($this->name, CheckboxType::class, [
                'label' => $this->description,
                'required' => $this->required,
                'constraints' => $this->required ? new Assert\IsTrue(['message' => __('This field is required.')]) : [],
            ]);
        $builder->get($this->name)->addModelTransformer(new IntToBooleanTransformer());
    }

    public function buildConfigurationForm(FormBuilderInterface $builder): void
    {
        $builder
            ->add('description', TextType::class, [
                'label' => __('Text next to the checkbox'),
                'constraints' => new Assert\NotBlank(),
            ])
            ->add('required', CheckboxType::class, [
                'label' => __('Checking this checkbox is mandatory.'),
                'required' => false,
            ])
            ->add('submit', SubmitType::class, [
                'label' => __('Modify field'),
            ]);
    }

    public function columnLabels(): array
    {
        return [$this->name => $this->description];
    }

    public function getFormData($value): array
    {
        return $this->export($value);
    }

    public function export($value): array
    {
        return [$this->name => $value ? 1 : 0];
    }
}
