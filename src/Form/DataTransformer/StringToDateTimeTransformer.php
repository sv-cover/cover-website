<?php 
namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToStringTransformer;

class StringToDateTimeTransformer implements DataTransformerInterface
{
    private DateTimeToStringTransformer $transformer;

    public function __construct(?string $inputTimezone = null, ?string $outputTimezone = null, string $format = 'Y-m-d H:i:s', ?string $parseFormat = null)
    {
        $this->transformer = new DateTimeToStringTransformer($inputTimezone, $outputTimezone, $format, $parseFormat);
    }

    public function transform(mixed $value): ?\DateTime
    {
        return $this->transformer->reverseTransform($value);
    }

    public function reverseTransform(mixed $value): ?string
    {
        $transformed = $this->transformer->transform($value);

        if (empty($transformed))
            return null;

        return $transformed;
    }
}
