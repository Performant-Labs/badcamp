<?php

declare(strict_types=1);

namespace Drupal\badcamp_sponsors;

/**
 * Builds the generated title of a coupon node.
 *
 * The title is "<code> — <sponsor> / <campaign>" (em dash, U+2014), with the
 * code trimmed and the sponsor and campaign names used verbatim.
 */
final class CouponTitleBuilder {

  /**
   * Builds a coupon title from its parts.
   *
   * @param string $code
   *   The coupon code; trimmed before the title is assembled.
   * @param string $sponsor_name
   *   The sponsor name, used verbatim.
   * @param string $campaign_name
   *   The campaign name, used verbatim.
   *
   * @return string
   *   The generated title.
   */
  public static function build(string $code, string $sponsor_name, string $campaign_name): string {
    return CouponCodeNormalizer::normalize($code) . ' — ' . $sponsor_name . ' / ' . $campaign_name;
  }

}
