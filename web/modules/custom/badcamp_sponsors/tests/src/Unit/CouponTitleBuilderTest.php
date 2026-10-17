<?php

declare(strict_types=1);

namespace Drupal\Tests\badcamp_sponsors\Unit;

use Drupal\badcamp_sponsors\CouponTitleBuilder;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the coupon title builder rule.
 *
 * A coupon node's title is generated on save as
 * "<code> — <sponsor name> / <campaign name>" (em dash, U+2014).
 */
#[Group('badcamp_sponsors')]
class CouponTitleBuilderTest extends UnitTestCase {

  /**
   * Tests the exact title shape.
   */
  public function testTitleShape(): void {
    $this->assertSame(
      'SAVE20 — Acme Corp / Fall Blast',
      CouponTitleBuilder::build('SAVE20', 'Acme Corp', 'Fall Blast')
    );
  }

  /**
   * Tests that the code is trimmed before the title is built.
   */
  public function testCodeIsTrimmed(): void {
    $this->assertSame(
      'SAVE20 — Acme Corp / Fall Blast',
      CouponTitleBuilder::build('  SAVE20  ', 'Acme Corp', 'Fall Blast')
    );
  }

  /**
   * Tests that sponsor and campaign names are used verbatim.
   */
  public function testNamesAreUsedVerbatim(): void {
    $this->assertSame(
      'EARLY-BIRD — Sponsor  Two Words / Campaign (Fall)',
      CouponTitleBuilder::build('EARLY-BIRD', 'Sponsor  Two Words', 'Campaign (Fall)')
    );
  }

}
