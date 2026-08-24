<?php

declare(strict_types=1);

namespace App\Validator;

use App\Repository\FeedbackTypeRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ActiveFeedbackTypeSlugValidator extends ConstraintValidator
{
    public function __construct(private readonly FeedbackTypeRepository $types)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ActiveFeedbackTypeSlug) {
            throw new UnexpectedTypeException($constraint, ActiveFeedbackTypeSlug::class);
        }

        if (!is_string($value) || '' === $value) {
            return;
        }

        if (null !== $this->types->findOneBy(['slug' => $value, 'isActive' => true])) {
            return;
        }

        $this->context->buildViolation($constraint->message)->addViolation();
    }
}
