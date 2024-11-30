<?php

namespace App\SignUp\Fields;

use App\Service\Incassomatic;
use App\SignUp\SignUpFieldInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

class BankAccountField implements SignUpFieldInterface
{
    public $name;

    public $required;

    public $autofill;

    public function __construct(
        private Incassomatic $incassomatic,
    ){
    }

    public static function getTypeLabel(): string
    {
        return __('Bank account (IBAN) field');
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
        $iban = $form->get($this->name . '-iban')->getData();

        // Clean IBAN for good measure
        $iban = \preg_replace('/[^A-Z0-9]/u', '', \strtoupper($iban));

        return \json_encode([
            'iban' => $iban,
            'bic' => $form->get($this->name . '-bic')->getData(),
        ]);
    }

    public function prefill(\DataIterMember $member): ?string
    {
        if (!$this->autofill)
            return null;

        try {
            $contracts = $this->incassomatic->getContracts($member);

            // Only use valid contracts
            $contract = \current(
                \array_filter($contracts, fn($c): bool => $c['is_geldig'])
            );

            if (!$contract)
                return null;

            return \json_encode(['iban' => $contract['iban'], 'bic' => $contract['bic']]);
        } catch (\Exception|\Error $exception) {
            throw $exception;
            return null;
        }
    }

    public function buildForm(FormBuilderInterface $builder): void
    {
        $builder
            ->add($this->name . '-iban', TextType::class, [
                'label' => __('IBAN'),
                'required' => $this->required,
                'constraints' => \array_filter([
                    $this->required ? new Assert\NotBlank() : null,
                    new Assert\Iban(),
                ]),
            ])
            ->add($this->name . '-bic', TextType::class, [
                'label' => __('BIC'),
                'required' => false,
                'constraints' => [
                    new Assert\Bic(),
                ],
                'help' => __("BIC is required if your IBAN does not start with 'NL'"), // This is never validated for better UX. Treasurer can always look it up.
            ]);
    }

    public function buildConfigurationForm(FormBuilderInterface $builder): void
    {
        $builder
            ->add('required', CheckboxType::class, [
                'label' => __('Filling in bank account (IBAN) is mandatory.'),
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
            $this->name . '-iban' => 'IBAN',
            $this->name . '-bic' => 'BIC'
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
            $this->name . '-iban' => $data['iban'] ?? '',
            $this->name . '-bic' => $data['bic'] ?? ''
        ];
    }
}
