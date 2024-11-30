<?php
namespace App\Form;

use App\Service\Authentication;
use App\Service\Database;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType as PasswordFieldType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class PasswordType extends AbstractType
{
    public function __construct(
        private Authentication $auth,
        private Database $db,
    ){
    }

    private function getMember(): \DataIterMember
    {
        if (!empty($options['member']))
            return $options['member'];
        else
            return $this->auth->getIdentity()->member();
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {

        if (!empty($options['confirm_current'])) {
            $builder->add('current', PasswordFieldType::class, [
                'label' => __('Current Password'),
                'required' => true,
                'constraints' => [
                    new Assert\Callback([$this, 'validateCurrent']),
                ],
            ]);
        }

        $builder
            ->add('password', RepeatedType::class, [
                'type' => PasswordFieldType::class,
                'invalid_message' => __('The two passwords are not the same.'),
                'required' => true,
                'first_options'  => ['label' => 'New Password'],
                'second_options' => ['label' => 'Repeat Password'],
                'constraints' => [
                    new Assert\Callback([$this, 'validatePassword']),
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => __('Change password'),
            ])
        ;

    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'member' => null,
            'confirm_current' => true,
        ]);
    }

    public function validateCurrent($value, ExecutionContextInterface $context, $payload): void
    {
        $model = $this->db->getModel('DataModelMember');
        $member = $this->getMember();

        if (!$model->test_password($member, $value))
            $context->buildViolation(__('That’s not your current password!'))
                ->atPath('current')
                ->addViolation();
    }

    public function validatePassword($value, ExecutionContextInterface $context, $payload): void
    {
        $model = $this->db->getModel('DataModelMember');
        $member = $this->getMember();

        if ($model->test_password($member, $value))
            $context->buildViolation(__('Your new password cannot be the same as your current password!'))
                ->atPath('password')
                ->addViolation();

        $effectivePassword = str_ireplace([$member['voornaam'], $member['achternaam'], 'cover', 'password'], '', $value);

        // Short passwords, or very common passwords, are stupid.
        if (strlen($effectivePassword) < 6)
            $context->buildViolation(__('Your password is too short or too predictable. Try to make it longer and with more different characters.'))
                ->atPath('password')
                ->addViolation();
    }
}
