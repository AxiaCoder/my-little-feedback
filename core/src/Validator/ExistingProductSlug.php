<?php

declare(strict_types=1);

namespace App\Validator;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * The value has to match an existing {@see \App\Entity\Product} slug.
 *
 * A constraint rather than a lookup in the controller, so that "this product
 * does not exist" arrives in the same list as "this message is too short"
 * (spec 01 §3.3). An unknown slug is a violation on the field, never a 404: the
 * route exists, the value inside it is what is wrong.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class ExistingProductSlug extends Constraint
{
    public string $message = 'No product matches this identifier.';
}
