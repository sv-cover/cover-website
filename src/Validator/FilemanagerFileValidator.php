<?php
namespace App\Validator;

use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class FilemanagerFileValidator extends ConstraintValidator
{
    public function __construct(
        private ContainerBagInterface $params,
    ) {
    }

    public function validate($value, Constraint $constraint)
    {
        // Keep Symfony happy
        if (!$constraint instanceof FilemanagerFile)
            throw new UnexpectedTypeException($constraint, FilemanagerFile::class);

        if (null === $value || '' === $value)
            return;

        if (!is_string($value))
            throw new UnexpectedValueException($value, 'string');

        // Only accept image file (using naive extension check)
        $ext = pathinfo(parse_url($value, PHP_URL_PATH), PATHINFO_EXTENSION);

        try {
            $allowed_exts = $this->params->get('app.filemanager_image_extensions');
        } catch (ParameterNotFoundException $e) {
            $allowed_exts = ['jpg', 'jpeg', 'png'];
        }

        if (!in_array(strtolower($ext), $allowed_exts))
            $this->context->buildViolation(__($constraint->extension_message))
                ->setParameter('{{ extension }}', implode(', ', $allowed_exts))
                ->addViolation();
    }
}
