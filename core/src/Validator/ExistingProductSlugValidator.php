<?php

declare(strict_types=1);

namespace App\Validator;

use App\Repository\ProductRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ExistingProductSlugValidator extends ConstraintValidator
{
    public function __construct(private readonly ProductRepository $products)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ExistingProductSlug) {
            throw new UnexpectedTypeException($constraint, ExistingProductSlug::class);
        }

        // Preceded by NotNull, Type and NotBlank inside a `Sequentially`, so a
        // value that reaches this point is a non-empty string. The guard is what
        // keeps that true if the chain is ever reordered.
        if (!is_string($value) || '' === $value) {
            return;
        }

        if (null !== $this->products->findOneBy(['slug' => $value])) {
            return;
        }

        $this->context->buildViolation($constraint->message)->addViolation();
    }
}
