<?php

declare(strict_types=1);

namespace Drupal\badcamp_sponsors;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Finds live coupons duplicating a given (code, sponsor, campaign).
 *
 * This is the single home of the one entity query that compares coupon
 * codes. It answers live coupons only: trash filters queries that carry no
 * explicit 'deleted' condition to live entities in the default active
 * context, so no deleted condition is added here.
 */
final class CouponDuplicateFinder {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Returns the ascending integer node IDs of live duplicate coupons.
   *
   * @param string $code
   *   The coupon code, normalized internally before comparison.
   * @param int|string $sponsor_id
   *   The sponsor node ID (entity IDs may arrive as numeric strings).
   * @param int|string $campaign_id
   *   The campaign node ID (entity IDs may arrive as numeric strings).
   * @param int|null $exclude_id
   *   A node ID to exclude from the result (the coupon being validated on
   *   update), or NULL to match every coupon.
   *
   * @return int[]
   *   The ascending integer node IDs of the live coupons sharing the
   *   normalized code, sponsor and campaign.
   */
  public function findLiveDuplicates(string $code, int|string $sponsor_id, int|string $campaign_id, ?int $exclude_id = NULL): array {
    $normalized = CouponCodeNormalizer::normalize($code);
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'coupon')
      ->condition('field_coupon_code.value', $normalized)
      ->condition('field_coupon_sponsor.target_id', (int) $sponsor_id)
      ->condition('field_coupon_campaign.target_id', (int) $campaign_id);
    if ($exclude_id !== NULL) {
      $query->condition('nid', $exclude_id, '<>');
    }
    $ids = array_map('intval', $query->execute());
    sort($ids, SORT_NUMERIC);
    return $ids;
  }

}
