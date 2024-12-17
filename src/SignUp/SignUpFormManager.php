<?php

namespace App\SignUp;

use App\DataIter\DataIterSignupEntry;
use App\DataIter\DataIterSignupField;
use App\DataIter\DataIterSignupForm;
use App\DataModel\DataModelSignUpField;
use App\SignUp\Fields;
use Psr\Container\ContainerInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Component\Uid\Uuid;


class SignUpFormManager implements ServiceSubscriberInterface
{
    public function __construct(
        private DataModelSignUpField $fieldModel,
        private ContainerInterface $locator,
        private FormFactoryInterface $formFactory,
    ){
    }

    public static function getSubscribedServices(): array
    {
        // Random order? No! This decides how they show up in the form editor.
        return [
            'text' => Fields\TextField::class,
            'checkbox' => Fields\CheckboxField::class,
            'choice' => Fields\ChoiceField::class,
            'name' => Fields\NameField::class,
            'address' => Fields\AddressField::class,
            'email' => Fields\EmailField::class,
            'phone' => Fields\PhoneField::class,
            'bankaccount' => Fields\BankAccountField::class,
            'editable' => Fields\EditableField::class,
        ];
    }

    protected function getField(DataIterSignupField $iter): SignUpFieldInterface
    {
        $field = $this->locator->get($iter['type']);
        $field->setName($iter['name']);
        $field->setConfiguration($iter['properties']);
        return $field;
    }

    protected function getFields(DataIterSignupForm $form): \Generator
    {
        foreach ($form->get_fields() as $field)
            yield $field->get_id() => $this->getField($field);
    }

    public function createField(DataIterSignupForm $form, string $type, ?callable $configure = null): DataIterSignupField
    {
        if (!isset(self::getSubscribedServices()[$type]))
            throw new \InvalidArgumentException('Unknown form field type');

        $iter = $this->fieldModel->new_iter([
            'form_id' => $form->get_id(),
            'name' => Uuid::v4()->toRfc4122(), // UUID v4 in RFC 4122 contains dashes and therefore never returns a numeric string. Numeric strings cause issues with arrays.
            'type' => $type,
            'properties' => '{}',
        ]);

        if ($configure) {
            $field = $this->getField($iter);
            $configure($field);
            $iter['properties'] = $field->getConfiguration();
        }

        return $iter;
    }

    public function getForm(DataIterSignupEntry $entry, array $defaults = []): FormInterface
    {
        $form = $entry['form'];

        $data = $defaults;

        if ($entry['member_id'])
            $data['member_id'] = $entry['member_id'];

        foreach ($this->getFields($form) as $id => $field) {
            $value = $entry->get_values()[$id] ?? null; // use get_values to skip cache
            $data = \array_merge($data, $field->getFormData($value));
        }

        $builder = $this->formFactory->createNamedBuilder(sprintf('sign-up-form-%s', $form->get_id()), FormType::class, $data);

        foreach ($this->getFields($form) as $id => $field)
            $field->buildForm($builder);

        $builder
            ->add('return_path', HiddenType::class)
            ->add('member_id', HiddenType::class)
            ->add('submit', SubmitType::class, [
                'label' => __('Sign me up'),
            ]);

        return $builder->getForm();
    }

    public function getConfigurationForm(DataIterSignupField $iter): FormInterface
    {
        $field = $this->getField($iter);

        $builder = $this->formFactory->createNamedBuilder(sprintf('sign-up-field-%s', $field->getName()), FormType::class, $field->getConfiguration());

        $field->buildConfigurationForm($builder);

        return $builder->getForm();
    }

    public function getConfigurationTemplate(DataIterSignupField $iter): string
    {
        $field = $this->getField($iter);

        return $field->getConfigurationTemplate();
    }

    public function getColumnLabels(DataIterSignupField $iter): array
    {
        $field = $this->getField($iter);

        return $field->columnLabels();
    }

    public function getTypeLabel(DataIterSignupField $iter): string
    {
        $field = $this->getField($iter);

        return self::getSubscribedServices()[$iter['type']]::getTypeLabel();
    }

    public function exportEntry(DataIterSignupEntry $entry): array
    {
        $data = [];

        foreach ($this->getFields($entry['form']) as $id => $field) {
            $value = $entry->get_values()[$id] ?? null;  // use get_values to skip cache
            $data = \array_merge($data, $field->export($value));
        }

        // Put that on the end
        $data['signed-up-on'] = $entry['created_on'];

        return $data;
    }

    public function prefillEntry(DataIterSignupEntry $entry): void
    {
        $values = [];

        foreach ($this->getFields($entry['form']) as $id => $field)
            $values[$id] = $field->prefill($entry['member']);

        $entry->set_values($values);
    }

    public function processEntry(DataIterSignupEntry $entry, FormInterface $form): void
    {
        $values = [];

        foreach ($this->getFields($entry['form']) as $id => $field)
            $values[$id] = $field->process($form);


        $entry->set_values($values);
    }

    // TODO SFY: get rid of this function
    public function renderEntryTable(DataIterSignupEntry $entry)
    {
        $rows = [];

        $data = $this->exportEntry($entry);

        foreach ($this->getFields($entry['form']) as $id => $field) {
            if ($field instanceof Fields\CheckboxField) {
                $label = $field->columnLabels();

                if (!empty($data[key($label)])) {
                    $rows[] = \sprintf(
                        '<tr><td style="text-align:left" colspan="2">✓ %s</td></tr>',
                        \markup_format_text(current($label)),
                    );
                } else {
                    // Just don't add a row for it :)
                }
            } else {
                foreach ($field->columnLabels() as $key => $label) {
                    $rows[] = \sprintf(
                        '<tr><th style="text-align:left">%s</th><td>%s</td></tr>',
                        \markup_format_text($label),
                        (
                            $data[$key] === '' || $data[$key] === null
                            ? '<em>left blank</em>'
                            : \markup_format_text($data[$key])
                        ),
                    );
                }
            }
        }

        return \sprintf('<table>%s</table>', implode('', $rows));
    }
}
