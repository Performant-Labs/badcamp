<?php

declare(strict_types=1);

namespace Drupal\badcamp_sponsors;

/**
 * Builds the reason shown when a coupon-referenced delete is refused.
 *
 * Single construction site of the coupon-count reason: both
 * badcamp_sponsors_node_access() (setting it on the access result) and
 * DeleteRefusalSubscriber (recognizing it on the thrown exception) go through
 * this class, so the wording exists in exactly one place. The wording is
 * frozen by the issue; only the bundle name and the live + trashed coupon
 * count vary.
 */
final class DeleteRefusalReason {

  /**
   * The bundles whose delete the coupon count can refuse.
   */
  private const BUNDLES = ['sponsor', 'campaign'];

  /**
   * The reason pattern, with the bundle name and coupon count placeholders.
   */
  private const PATTERN = 'This %s is referenced by %d coupon(s), including coupons in trash.';

  /**
   * Builds the delete-refusal reason for a bundle.
   *
   * @param string $bundle
   *   The node bundle being deleted ('sponsor' or 'campaign').
   * @param int $count
   *   The number of referencing coupons, live and trashed.
   *
   * @return string
   *   The reason.
   */
  public static function build(string $bundle, int $count): string {
    return sprintf(self::PATTERN, $bundle, $count);
  }

  /**
   * Whether a reason string was produced by this class.
   *
   * Tests provenance only: the count is matched, never re-derived, so a
   * caller holding an arbitrary reason string (e.g. an exception message
   * from another module) can tell it apart from this module's reasons
   * without counting anything itself.
   *
   * @param string $reason
   *   The reason string to test.
   *
   * @return bool
   *   TRUE if the string is this module's coupon-count reason for one of the
   *   refused bundles, FALSE otherwise.
   */
  public static function matches(string $reason): bool {
    foreach (self::BUNDLES as $bundle) {
      // Quote the pattern verbatim first, then substitute its two
      // placeholders: the bundle with its literal name and the count with
      // the regex for a non-negative integer.
      $regex = '/^' . str_replace(
        ['%s', '%d'],
        [preg_quote($bundle, '/'), '\d+'],
        preg_quote(self::PATTERN, '/')
      ) . '$/';
      if (preg_match($regex, $reason) === 1) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
