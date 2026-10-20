<?php

declare(strict_types=1);

namespace Drupal\badcamp_sponsors\Plugin\Validation\Constraint;

use Drupal\badcamp_sponsors\CouponCodeNormalizer;
use Drupal\node\NodeInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the coupon code uniqueness per (sponsor, campaign).
 */
final class CouponCodeUniqueConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    \assert($constraint instanceof CouponCodeUniqueConstraint);
    if (!$value instanceof NodeInterface || $value->bundle() !== 'coupon') {
      return;
    }

    $code = CouponCodeNormalizer::normalize((string) ($value->get('field_coupon_code')->value ?? ''));
    $sponsor_id = $value->get('field_coupon_sponsor')->target_id;
    $campaign_id = $value->get('field_coupon_campaign')->target_id;
    // Missing codes or references are reported by the required-field and
    // entity-reference constraints; nothing to compare here.
    if ($code === '' || $sponsor_id === NULL || $campaign_id === NULL) {
      return;
    }

    $duplicates = \Drupal::service('badcamp_sponsors.coupon_duplicate_finder')
      ->findLiveDuplicates($code, $sponsor_id, $campaign_id, !$value->isNew() ? (int) $value->id() : NULL);
    if ($duplicates !== []) {
      $this->context->buildViolation($constraint->message)
        ->setParameter('%code', $code)
        ->atPath('field_coupon_code')
        ->addViolation();
    }
  }

}
