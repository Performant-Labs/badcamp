<?php

declare(strict_types=1);

namespace Drupal\Tests\badcamp_sponsors\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests coupon view privacy through the node access contract (AC 5).
 */
#[Group('badcamp_sponsors')]
class CouponAccessTest extends KernelTestBase {

  use UserCreationTrait {
    createUser as drupalCreateUser;
  }

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['user']);
    $this->container->get('module_installer')->install(['badcamp_sponsors']);
    $this->installSchema('node', 'node_access');
    // User 1 bypasses node access; create it first so every account
    // the assertions is a regular user.
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
   * Tests that viewing a coupon requires the module permission.
   */
  public function testCouponViewRequiresPermission(): void {
    $sponsor = $this->createSponsor('Acme Corp');
    $campaign = $this->createCampaign('Fall Blast');
    $coupon = $this->createCoupon('SAVE20', $sponsor, $campaign);

    $without_permission = $this->drupalCreateUser(['access content']);
    $with_permission = $this->drupalCreateUser([
      'access content',
      'view coupon content',
    ]);

    // 403-equivalent: a forbidden access result for a user without the
    // coupon permission.
    $result = $coupon->access('view', $without_permission, TRUE);
    $this->assertTrue($result->isForbidden(), 'Viewing a coupon without the permission is forbidden.');
    $this->assertFalse($coupon->access('view', $without_permission));

    // With the permission the coupon is viewable.
    $this->assertTrue(
      $coupon->access('view', $with_permission, TRUE)->isAllowed(),
      'Viewing a coupon with the permission is allowed.'
    );
  }

  /**
   * Tests that the hook does not leak to the public types.
   */
  public function testOtherTypesStayPublic(): void {
    $sponsor = $this->createSponsor('Acme Corp');
    $campaign = $this->createCampaign('Fall Blast');

    $plain = $this->drupalCreateUser(['access content']);
    $this->assertTrue(
      $sponsor->access('view', $plain, TRUE)->isAllowed(),
      'Published sponsors stay viewable by ordinary users.'
    );
    $this->assertTrue(
      $campaign->access('view', $plain, TRUE)->isAllowed(),
      'Published campaigns stay viewable by ordinary users.'
    );
  }

}
