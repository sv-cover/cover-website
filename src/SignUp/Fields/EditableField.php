<?php

namespace App\SignUp\Fields;

use App\SignUp\SignUpFieldInterface;
use App\Form\Type\MarkupType;
use App\Form\Type\PresentationType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormBuilderInterface;

class EditableField implements SignUpFieldInterface
{
    public $name;

    public $content;

    public static function getTypeLabel(): string
    {
        return __('Titles and text (layout)');
    }

    public function getConfiguration(): array
    {
        return [
            'content' => $this->content,
        ];
    }

    public function setConfiguration(array $configuration): void
    {
        $this->content = $configuration['content'] ?? '';
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
        return null;
    }

    public function prefill(\DataIterMember $member): ?string
    {
        return null;
    }

    public function buildForm(FormBuilderInterface $builder): void
    {
        $builder
            ->add($this->name, PresentationType::class, [
                'content' => $this->content,
            ]);
    }

    public function buildConfigurationForm(FormBuilderInterface $builder): void
    {
        $builder
            ->add('content', MarkupType::class, [
                'label' => __('Content'),
                'required' => false,
            ])
            ->add('submit', SubmitType::class, [
                'label' => __('Modify field'),
            ]);
    }

    public function columnLabels(): array
    {
        return [];
    }

    public function getFormData($value): array
    {
        return $this->export($value);
    }

    public function export($value): array
    {
        return [];
    }
}