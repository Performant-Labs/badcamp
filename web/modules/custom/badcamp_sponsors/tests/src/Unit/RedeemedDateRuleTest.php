<?php

declare(strict_types=1);

namespace Drupal\Tests\badcamp_sponsors\Unit;

use Drupal\badcamp_sponsors\RedeemedDateRule;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the redeemed-date rule as pure logic.
 *
 * The "Redeemed date" is required when "Redeemed" is checked, and must be
 * empty when "Redeemed" is unchecked.
 */
#[Group('badcamp_sponsors')]
class RedeemedDateRuleTest extends UnitTestCase {

  /**
   * Tests that a redeemed coupon without a redeemed date is a violation.
   */
  public function testRedeemedWithoutDateIsInvalid(): void {
    $this->assertSame(
      ['The "Redeemed date" must be set when "Redeemed" is checked.'],
      RedeemedDateRule::validate(TRUE, NULL)
    );
    $this->assertSame(
      ['The "Redeemed date" must be set when "Redeemed" is checked.'],
      RedeemedDateRule::validate(TRUE, '')
    );
  }

  /**
   * Tests that an unredeemed coupon with a redeemed date is a violation.
   */
  public function testNotRedeemedWithDateIsInvalid(): void {
    $this->assertSame(
      ['The "Redeemed date" must be empty when "Redeemed" is unchecked.'],
      RedeemedDateRule::validate(FALSE, '2026-10-16T09:30:00')
    );
  }

  /**
   * Tests that the two consistent combinations produce no violations.
   */
  public function testConsistentCombinationsAreValid(): void {
    $this->assertSame([], RedeemedDateRule::validate(TRUE, '2026-10-16T09:30:00'));
    $this->assertSame([], RedeemedDateRule::validate(FALSE, NULL));
    $this->assertSame([], RedeemedDateRule::validate(FALSE, ''));
  }

}
