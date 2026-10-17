<?php

declare(strict_types=1);

namespace Drupal\badcamp_sponsors;

/**
 * Normalizes coupon codes before they are stored or compared.
 *
 * Coupon codes are provided by sponsors, so the module never generates or
 * rewrites them: it only trims surrounding whitespace (including tabs and
 * newlines) so that padded entries cannot evade the per-(sponsor, campaign)
 * uniqueness check. Internal whitespace is preserved verbatim.
 */
final class CouponCodeNormalizer {

  /**
   * Trims surrounding whitespace from a coupon code.
   *
   * @param string $code
   *   The coupon code as entered by the editor.
   *
   * @return string
   *   The code with leading and trailing whitespace removed.
   */
  public static function normalize(string $code): string {
    return trim($code);
  }

}
