<?php

declare(strict_types=1);

namespace Drupal\Tests\badcamp_sponsors\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the coupon duplicate finder service (AC 1).
 *
 * The finder is the single home of the one entity query that compares
 * field_coupon_code.value. It returns the ascending integer node IDs of the
 * LIVE coupons sharing a normalized (trimmed) code, sponsor and campaign,
 * never trashed coupons, and honours the optional exclude id.
 */
#[Group('badcamp_sponsors')]
class CouponDuplicateFinderTest extends KernelTestBase {

  use UserCreationTrait {
    createUser as drupalCreateUser;
  }

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'trash'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    // System config provides the date formats the token system needs when
    // pathauto runs on node save.
    $this->installConfig(['user', 'system']);
    // Trash must be told about node before the node entity schema exists,
    // otherwise the 'deleted' base field is missing its column.
    $this->config('trash.settings')
      ->set('enabled_entity_types', ['node' => []])
      ->save();
    $this->container->get('module_installer')->install(['badcamp_sponsors']);
    // User 1 bypasses node access; create it first so the fixtures are owned
    // by a regular account, matching the module's other Kernel tests.
    $this->drupalCreateUser();
  }

  /**
   * Creates and saves a sponsor node.
   */
  protected function createSponsor(string $title): NodeInterface {
    $sponsor = Node::create([
      'type' => 'sponsor',
      'title' => $title,
      'status' => 1,
    ]);
    $sponsor->save();
    return $sponsor;
  }

  /**
   * Creates and saves a campaign node.
   */
  protected function createCampaign(string $title): NodeInterface {
    $campaign = Node::create([
      'type' => 'campaign',
      'title' => $title,
      'status' => 1,
    ]);
    $campaign->save();
    return $campaign;
  }

  /**
   * Creates and saves a coupon node.
   */
  protected function createCoupon(string $code, NodeInterface $sponsor, NodeInterface $campaign): NodeInterface {
    $coupon = Node::create([
      'type' => 'coupon',
      'title' => 'Placeholder title',
      'status' => 1,
      'field_coupon_code' => $code,
      'field_coupon_sponsor' => ['target_id' => $sponsor->id()],
      'field_coupon_campaign' => ['target_id' => $campaign->id()],
    ]);
    $coupon->save();
    return $coupon;
  }

  /**
   * Returns the finder service under test.
   */
  protected function finder(): object {
    return \Drupal::service('badcamp_sponsors.coupon_duplicate_finder');
  }

  /**
   * Tests that live coupons with the same normalized triple are returned.
   *
   * The result must be the ascending integer node IDs of exactly the live
   * coupons whose trimmed code, sponsor and campaign all match.
   */
  public function testFindLiveDuplicatesReturnsMatchingLiveCouponsAscending(): void {
    $sponsor_a = $this->createSponsor('Acme Corp');
    $sponsor_b = $this->createSponsor('Beta LLC');
    $campaign_a = $this->createCampaign('Fall Blast');
    $campaign_b = $this->createCampaign('Winter Sale');

    $a1 = $this->createCoupon('SAVE20', $sponsor_a, $campaign_a);
    // A padded code is stored trimmed, so it matches 'SAVE20'.
    $a2 = $this->createCoupon('  SAVE20  ', $sponsor_a, $campaign_a);
    // Non-matching triples: different code, sponsor or campaign.
    $this->createCoupon('SAVE99', $sponsor_a, $campaign_a);
    $this->createCoupon('SAVE20', $sponsor_b, $campaign_a);
    $this->createCoupon('SAVE20', $sponsor_a, $campaign_b);

    $result = $this->finder()->findLiveDuplicates('SAVE20', $sponsor_a->id(), $campaign_a->id());

    $this->assertSame(
      [(int) $a1->id(), (int) $a2->id()],
      $result,
      'The finder returns the ascending integer IDs of the live coupons with the same trimmed code, sponsor and campaign.'
    );
  }

  /**
   * Tests that trashed coupons are never returned as live duplicates.
   */
  public function testFindLiveDuplicatesExcludesTrashedCoupons(): void {
    $sponsor = $this->createSponsor('Acme Corp');
    $campaign = $this->createCampaign('Fall Blast');
    $live = $this->createCoupon('SAVE20', $sponsor, $campaign);
    $trashed = $this->createCoupon('SAVE20', $sponsor, $campaign);
    $trashed->delete();

    $result = $this->finder()->findLiveDuplicates('SAVE20', $sponsor->id(), $campaign->id());

    $this->assertSame(
      [(int) $live->id()],
      $result,
      'A trashed coupon is never returned as a live duplicate.'
    );
  }

  /**
   * Tests that the exclude id is honoured.
   */
  public function testFindLiveDuplicatesHonoursExcludeId(): void {
    $sponsor = $this->createSponsor('Acme Corp');
    $campaign = $this->createCampaign('Fall Blast');
    $a1 = $this->createCoupon('SAVE20', $sponsor, $campaign);
    $a2 = $this->createCoupon('SAVE20', $sponsor, $campaign);

    $this->assertSame(
      [(int) $a2->id()],
      $this->finder()->findLiveDuplicates('SAVE20', $sponsor->id(), $campaign->id(), (int) $a1->id()),
      'Excluding the first coupon returns the second.'
    );
    $this->assertSame(
      [(int) $a1->id()],
      $this->finder()->findLiveDuplicates('SAVE20', $sponsor->id(), $campaign->id(), (int) $a2->id()),
      'Excluding the second coupon returns the first.'
    );
  }

  /**
   * Tests that numeric-string entity IDs are accepted.
   */
  public function testFindLiveDuplicatesAcceptsNumericStringIds(): void {
    $sponsor = $this->createSponsor('Acme Corp');
    $campaign = $this->createCampaign('Fall Blast');
    $coupon = $this->createCoupon('SAVE20', $sponsor, $campaign);

    $result = $this->finder()->findLiveDuplicates(
      'SAVE20',
      (string) $sponsor->id(),
      (string) $campaign->id()
    );

    $this->assertSame(
      [(int) $coupon->id()],
      $result,
      'The finder accepts entity IDs given as numeric strings.'
    );
  }

}
