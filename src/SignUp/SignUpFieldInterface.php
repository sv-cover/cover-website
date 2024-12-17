<?php

namespace App\SignUp;

use App\DataIter\DataIterMember;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormBuilderInterface;

interface SignUpFieldInterface
{
    public static function getTypeLabel(): string;

    public function getConfiguration(): array;

    public function setConfiguration(array $configuration): void;

    public function getConfigurationTemplate(): string;

    public function getName(): string;

    public function setName(string $name): void;

    // Pick the value from the post_data associative array and, if valid, return
    // the content as how it has to be saved in the database. If it didn't
    // validate, return an error.
    public function process(FormInterface $form): ?string;

    // Suggest a value (like process) for a logged-in member
    public function prefill(DataIterMember $member): ?string;

    // Add field to a symfony form
    public function buildForm(FormBuilderInterface $form_builder): void;

    public function buildConfigurationForm(FormBuilderInterface $builder): void;

    // Export it to a CSV (as an array with name => text value)
    public function export($value): array;

    // Export it to a CSV (as an array with name => text value)
    public function getFormData($value): array;

    // Get field info as name => info
    public function columnLabels(): array;
}
