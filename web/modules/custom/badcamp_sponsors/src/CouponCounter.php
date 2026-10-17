<?php

declare(strict_types=1);

namespace Drupal\badcamp_sponsors;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Counts the coupons referencing a sponsor or campaign.
 *
 * Counting is trash-aware: coupons sitting in trash still reference their
 * sponsor and campaign, and a sponsor cannot be deleted while a trashed
 * coupon points at it. Because trash is only a soft dependency of this
 * module, the trashed half of the count runs solely when the 'deleted' base
 * field actually exists on the node entity type; otherwise the counter
 * degrades to live-only counting.
 *
 * The count is deliberately expressed as two entity queries split on the
 * 'deleted' base field (live + trashed), summed: trash only filters queries
 * that carry no explicit 'deleted' condition, so each query stays on the
 * platform's entity query API. No raw SQL, no joins.
 */
final class CouponCounter {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
  ) {}

  /**
   * Counts the coupons referencing a sponsor (live and trashed).
   *
   * @param \Drupal\node\NodeInterface|int|string $sponsor
   *   The sponsor node, or its node ID (entity IDs may arrive as numeric
   *   strings).
   *
   * @return int
   *   The number of referencing coupons.
   */
  public function countBySponsor(NodeInterface|int|string $sponsor): int {
    return $this->countByReference('field_coupon_sponsor', $sponsor);
  }

  /**
   * Counts the coupons referencing a campaign (live and trashed).
   *
   * @param \Drupal\node\NodeInterface|int|string $campaign
   *   The campaign node, or its node ID (entity IDs may arrive as numeric
   *   strings).
   *
   * @return int
   *   The number of referencing coupons.
   */
  public function countByCampaign(NodeInterface|int|string $campaign): int {
    return $this->countByReference('field_coupon_campaign', $campaign);
  }

  /**
   * Counts coupons referencing a node through a given entity reference field.
   *
   * @param string $field_name
   *   The entity reference field on coupons ('field_coupon_sponsor' or
   *   'field_coupon_campaign').
   * @param \Drupal\node\NodeInterface|int|string $reference
   *   The referenced node, or its node ID.
   *
   * @return int
   *   The number of live coupons plus, when trash is available, the number of
   *   trashed coupons referencing the node.
   */
  private function countByReference(string $field_name, NodeInterface|int|string $reference): int {
    $entity_id = $reference instanceof NodeInterface ? (int) $reference->id() : (int) $reference;
    $count = $this->countCoupons($field_name, $entity_id, FALSE);
    if ($this->trashedCouponsExist()) {
      $count += $this->countCoupons($field_name, $entity_id, TRUE);
    }
    return $count;
  }

  /**
   * Runs one coupon count query, live-only or trashed-only.
   *
   * @param string $field_name
   *   The entity reference field on coupons.
   * @param int $entity_id
   *   The referenced node ID.
   * @param bool $trashed
   *   FALSE counts only live coupons (trash, when enabled for node, filters
   *   queries without an explicit 'deleted' condition itself); TRUE counts
   *   only trashed coupons through an explicit 'deleted' existence filter,
   *   which trash's query alter respects and skips.
   *
   * @return int
   *   The number of matching coupons.
   */
  private function countCoupons(string $field_name, int $entity_id, bool $trashed): int {
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'coupon')
      ->condition("$field_name.target_id", $entity_id);
    if ($trashed) {
      // Trash marks soft-deleted entities with a non-NULL 'deleted' value;
      // live entities carry NULL.
      $query->exists('deleted');
    }
    return (int) $query->count()->execute();
  }

  /**
   * Returns whether trashed coupons can be counted on this site.
   *
   * @return bool
   *   TRUE when the trash module is present and the node entity type carries
   *   the 'deleted' base field it filters on.
   */
  private function trashedCouponsExist(): bool {
    // Trash is a soft dependency: its service must be resolved through the
    // locator (not injected), because the container would fail to build on
    // sites without it.
    // phpcs:ignore
    if (!\Drupal::hasService('Drupal\trash\TrashManagerInterface')) {
      return FALSE;
    }
    return isset($this->entityFieldManager->getFieldDefinitions('node', 'coupon')['deleted']);
  }

}
