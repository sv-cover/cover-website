<?php
namespace App\Validator;

use App\Service\Database;
use App\Exception\NotFoundException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class MemberValidator extends ConstraintValidator
{
    public function __construct(
        private Database $db,
    ) {
    }

    public function validate($value, Constraint $constraint): void
    {
        // Keep Symfony happy
        if (!$constraint instanceof Member)
            throw new UnexpectedTypeException($constraint, Member::class);

        if (null === $value)
            return;

        if (!is_int($value))
            throw new UnexpectedValueException($value, 'int');

        try {
            $member = $this->db->getModel('DataModelMember')->get_iter($value);
        } catch (NotFoundException $e) {
            $this->context->buildViolation(__($constraint->member_not_found_message))
                ->addViolation();
        }

        // TODO: is this even possible?
        if (empty($member))
            $this->context->buildViolation(__($constraint->member_not_found_message))
                ->addViolation();
    }
}
