<?php

declare(strict_types=1);

namespace Drupal\Tests\badcamp_sponsors\Unit;

use Drupal\badcamp_sponsors\CouponCodeNormalizer;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the coupon code normalizer rule.
 *
 * Coupon codes are sponsor-provided and are trimmed before they are stored
 * and before they are compared for uniqueness.
 */
#[Group('badcamp_sponsors')]
class CouponCodeNormalizerTest extends UnitTestCase {

  /**
   * Tests trimming of leading and trailing whitespace.
   */
  public function testTrimsSurroundingWhitespace(): void {
    $this->assertSame('SAVE20', CouponCodeNormalizer::normalize('  SAVE20  '));
    $this->assertSame('SAVE20', CouponCodeNormalizer::normalize("\t SAVE20 \n"));
  }

  /**
   * Tests that an already-clean code is returned unchanged.
   */
  public function testCleanCodeIsUnchanged(): void {
    $this->assertSame('SAVE20', CouponCodeNormalizer::normalize('SAVE20'));
    $this->assertSame('TWO WORDS', CouponCodeNormalizer::normalize('TWO WORDS'));
  }

  /**
   * Tests that internal whitespace is preserved, not collapsed.
   */
  public function testInternalWhitespaceIsPreserved(): void {
    $this->assertSame('TWO  WORDS', CouponCodeNormalizer::normalize(' TWO  WORDS '));
  }

  /**
   * Tests the empty string edge case.
   */
  public function testEmptyString(): void {
    $this->assertSame('', CouponCodeNormalizer::normalize('    '));
  }

}
