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

    $query = \Drupal::entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'coupon')
      ->condition('field_coupon_code.value', $code)
      ->condition('field_coupon_sponsor.target_id', $sponsor_id)
      ->condition('field_coupon_campaign.target_id', $campaign_id);
    if (!$value->isNew()) {
      $query->condition('nid', $value->id(), '<>');
    }
    if ($query->range(0, 1)->execute() !== []) {
      $this->context->buildViolation($constraint->message)
        ->setParameter('%code', $code)
        ->atPath('field_coupon_code')
        ->addViolation();
    }
  }

}
