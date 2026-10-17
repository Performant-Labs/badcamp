<?php

declare(strict_types=1);

namespace Drupal\badcamp_sponsors;

/**
 * The redeemed / redeemed-date consistency rule, as pure logic.
 *
 * The "Redeemed date" must be set when "Redeemed" is checked, and must be
 * empty when "Redeemed" is unchecked. Consistent combinations produce no
 * violations.
 */
final class RedeemedDateRule {

  /**
   * Validates a redeemed / redeemed-date combination.
   *
   * @param bool $redeemed
   *   Whether the coupon is marked redeemed.
   * @param string|null $redeemed_date
   *   The stored redeemed date, if any.
   *
   * @return string[]
   *   The violation messages; empty when the combination is consistent.
   */
  public static function validate(bool $redeemed, ?string $redeemed_date): array {
    if ($redeemed) {
      if ($redeemed_date === NULL || $redeemed_date === '') {
        return ['The "Redeemed date" must be set when "Redeemed" is checked.'];
      }
      return [];
    }
    if ($redeemed_date !== NULL && $redeemed_date !== '') {
      return ['The "Redeemed date" must be empty when "Redeemed" is unchecked.'];
    }
    return [];
  }

}
