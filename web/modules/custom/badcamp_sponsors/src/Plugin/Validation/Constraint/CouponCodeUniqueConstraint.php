<?php

declare(strict_types=1);

namespace Drupal\badcamp_sponsors\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Rejects a coupon code already used for the same sponsor and campaign.
 */
#[Constraint(
  id: 'CouponCodeUnique',
  label: new TranslatableMarkup('Coupon code unique per sponsor and campaign', [], ['context' => 'Validation'])
)]
class CouponCodeUniqueConstraint extends SymfonyConstraint {

  /**
   * The violation message.
   *
   * @var string
   */
  public string $message = 'The coupon code %code is already used for this sponsor and campaign.';

}
