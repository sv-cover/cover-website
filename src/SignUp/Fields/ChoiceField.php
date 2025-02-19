<?php

namespace App\SignUp\Fields;

use App\DataIter\DataIterMember;
use App\SignUp\SignUpFieldInterface;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

class ChoiceField implements SignUpFieldInterface
{
    public $name;

    public $description;

    public $options;

    public $required;

    public $allow_multiple;

    public static function getTypeLabel(): string
    {
        return __('Multiple choice question');
    }

    public function getConfiguration(): array
    {
        return [
            'required' => $this->required,
            'allow_multiple' => $this->allow_multiple,
            'description' => $this->description,
            'options' => \array_values($this->options)
        ];
    }

    public function setConfiguration(array $configuration): void
    {
        $this->required = $configuration['required'] ?? false;
        $this->allow_multiple = $configuration['allow_multiple'] ?? false;
        $this->description = $configuration['description'] ?? '';
        $this->options = $configuration['options'] ?? [];
    }

    public function getConfigurationTemplate(): string
    {
        return 'sign_ups/configuration/_choice_field.html.twig';
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
        return \json_encode($form->get($this->name)->getData());
    }

    public function prefill(DataIterMember $member): ?string
    {
        return null;
    }

    public function buildForm(FormBuilderInterface $builder): void
    {
        $builder
            ->add($this->name, ChoiceType::class, [
                'label' => $this->description,
                'required' => $this->required,
                'constraints' => $this->required ? new Assert\NotBlank(['message' => $this->allow_multiple ? __('Pick at least one option.') : __('Pick an option.')]) : [],
                'choices' => array_combine($this->options, $this->options),
                'expanded' => true, // It would be nice to make these chips, but chips only work well with short labels and that's not guaranteed here.
                'placeholder' => false, // Prevent "None" option when !required && !allow_multiple
                'multiple' => $this->allow_multiple
            ]);
        $builder->get($this->name)->addModelTransformer(new CallbackTransformer(
            function ($value) {
                if ($this->allow_multiple)
                {
                    // ChoiceField expects array if multiple
                    if (\is_array($value))
                        return $value;
                    return [$value];
                }
                else
                {
                    // ChoiceField does not expect an array if not multiple
                    if (!\is_array($value))
                        return $value;
                    if (count($value) == 0)
                        return '';
                    return $value[0];
                }
            },
            function ($value) {
                // We always expect an array
                if (\is_array($value))
                    return $value;
                return [$value];
            },
        ));
    }

    public function buildConfigurationForm(FormBuilderInterface $builder): void
    {
        $builder
            ->add('description', TextType::class, [
                'label' => __('Label above the options'),
                'constraints' => new Assert\NotBlank(),
                'required' => false,
            ])
            ->add('options', CollectionType::class, [
                'label' => __('Options'),
                'entry_type' => TextType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'delete_empty' =>  function ($value = null) {
                    return empty($value);
                },
            ])
            ->add('allow_multiple', CheckboxType::class, [
                'label' => __('Allow multiple options to be picked.'),
                'required' => false,
            ])
            ->add('required', CheckboxType::class, [
                'label' => __('Picking an option is mandatory.'),
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
        return [$this->name => (array) \json_decode($value ?? '', true)];
    }

    public function export($value): array
    {
        $options = (array) \json_decode($value ?? '', true);
        return [$this->name => implode('; ', $options)];
    }
}
