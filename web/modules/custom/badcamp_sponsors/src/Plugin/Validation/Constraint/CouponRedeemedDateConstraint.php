<?php

declare(strict_types=1);

namespace Drupal\badcamp_sponsors\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Requires the redeemed / redeemed-date combination to be consistent.
 */
#[Constraint(
  id: 'CouponRedeemedDate',
  label: new TranslatableMarkup('Coupon redeemed date consistency', [], ['context' => 'Validation'])
)]
class CouponRedeemedDateConstraint extends SymfonyConstraint {

}
