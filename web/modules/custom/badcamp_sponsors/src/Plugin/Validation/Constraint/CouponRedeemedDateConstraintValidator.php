<?php

declare(strict_types=1);

namespace Drupal\badcamp_sponsors\Plugin\Validation\Constraint;

use Drupal\badcamp_sponsors\RedeemedDateRule;
use Drupal\node\NodeInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the redeemed / redeemed-date consistency through the pure rule.
 */
final class CouponRedeemedDateConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    \assert($constraint instanceof CouponRedeemedDateConstraint);
    if (!$value instanceof NodeInterface || $value->bundle() !== 'coupon') {
      return;
    }

    $redeemed = (bool) $value->get('field_coupon_redeemed')->value;
    $redeemed_date = $value->get('field_coupon_redeemed_date')->value;

    $messages = RedeemedDateRule::validate(
      $redeemed,
      $redeemed_date === NULL ? NULL : (string) $redeemed_date
    );
    foreach ($messages as $message) {
      $this->context->buildViolation($message)
        ->atPath('field_coupon_redeemed_date')
        ->addViolation();
    }
  }

}
