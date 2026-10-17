<?php

declare(strict_types=1);

namespace Drupal\Tests\badcamp_sponsors\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that sponsors/campaigns with coupons cannot be deleted (AC 4).
 *
 * Deleting must be blocked through node 'delete' access (the
 * hook_ENTITY_TYPE_access contract), including while the referencing coupon
 * sits in trash, and the trash-aware counter must include trashed coupons.
 */
#[Group('badcamp_sponsors')]
class DeleteBlockingTest extends KernelTestBase {

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
   * Hard-deletes a node even though trash is enabled for node.
   */
  protected function hardDelete(NodeInterface $node): void {
    // Trash 3.x exposes the manager as 'trash.manager' (plus its interface
    // alias); there is no 'trash' service id.
    $this->container->get('trash.manager')->executeInTrashContext(
      'ignore',
      static function () use ($node): void {
        $node->delete();
      }
    );
  }

  /**
   * Creates two sponsors, two campaigns and two coupons (one later trashed).
   *
   * @return array
   *   [sponsor_a, sponsor_b, campaign_a, campaign_b, live, trashed]
   */
  protected function createCouponFixtures(): array {
    $sponsor_a = $this->createSponsor('Acme Corp');
    $sponsor_b = $this->createSponsor('Beta LLC');
    $campaign_a = $this->createCampaign('Fall Blast');
    $campaign_b = $this->createCampaign('Winter Sale');

    // A live coupon referencing sponsor A / campaign A.
    $live = $this->createCoupon('SAVE20', $sponsor_a, $campaign_a);
    // A coupon referencing sponsor A / campaign B, then put in trash: with
    // trash enabled for node and the trash context 'active' (the default),
    // delete() soft-deletes.
    $trashed = $this->createCoupon('SAVE21', $sponsor_a, $campaign_b);
    $trashed->delete();

    return [$sponsor_a, $sponsor_b, $campaign_a, $campaign_b, $live, $trashed];
  }

  /**
   * Tests the trash-aware coupon counter.
   */
  public function testCouponCounterIsTrashAware(): void {
    [$sponsor_a, $sponsor_b, $campaign_a, $campaign_b] = $this->createCouponFixtures();
    $counter = $this->container->get('badcamp_sponsors.coupon_counter');

    $this->assertSame(2, $counter->countBySponsor($sponsor_a), 'Sponsor A is referenced by a live and a trashed coupon.');
    $this->assertSame(2, $counter->countBySponsor($sponsor_a->id()), 'The counter accepts node IDs too.');
    $this->assertSame(0, $counter->countBySponsor($sponsor_b), 'Sponsor B is unreferenced.');
    $this->assertSame(1, $counter->countByCampaign($campaign_a), 'Campaign A has one live coupon.');
    $this->assertSame(1, $counter->countByCampaign($campaign_b), 'Trashed coupons count for campaigns too.');
  }

  /**
   * Tests that delete access is forbidden while coupons reference the node.
   */
  public function testDeleteIsBlockedWhileReferenced(): void {
    [$sponsor_a, $sponsor_b, , $campaign_b] = $this->createCouponFixtures();

    $manager = $this->drupalCreateUser([
      'access content',
      'delete any sponsor content',
      'delete any campaign content',
      'delete any coupon content',
    ]);

    // The user holds the delete permission: only the module's access hook
    // can explain the forbidden result.
    $result = $sponsor_a->access('delete', $manager, TRUE);
    $this->assertTrue($result->isForbidden(), 'Deleting a sponsor with coupons (one trashed) is forbidden.');
    $this->assertFalse($sponsor_a->access('delete', $manager));

    $this->assertTrue(
      $campaign_b->access('delete', $manager, TRUE)->isForbidden(),
      'Deleting a campaign referenced only by a trashed coupon is forbidden.'
    );

    // Unreferenced nodes keep normal delete access.
    $this->assertTrue(
      $sponsor_b->access('delete', $manager, TRUE)->isAllowed(),
      'Deleting an unreferenced sponsor is allowed.'
    );

    // The hook never grants access: a user without the permission is denied
    // regardless.
    $plain = $this->drupalCreateUser(['access content']);
    $this->assertFalse(
      $sponsor_b->access('delete', $plain, TRUE)->isAllowed(),
      'A user without the delete permission cannot delete sponsors.'
    );
  }

  /**
   * Tests that delete access returns once no coupon references the node.
   */
  public function testDeleteIsAllowedOnceUnreferenced(): void {
    [$sponsor_a, , , , $live, $trashed] = $this->createCouponFixtures();

    $manager = $this->drupalCreateUser([
      'access content',
      'delete any sponsor content',
      'delete any coupon content',
    ]);
    $this->assertTrue(
      $sponsor_a->access('delete', $manager, TRUE)->isForbidden(),
      'Blocked while referenced.'
    );

    // Move the live coupon away and hard-delete the trashed one.
    $other_sponsor = $this->createSponsor('Gamma Inc');
    $live->set('field_coupon_sponsor', ['target_id' => $other_sponsor->id()]);
    $live->save();
    $this->hardDelete($trashed);

    // Access results are cached per entity: clear them before re-checking.
    $this->container->get('entity_type.manager')
      ->getAccessControlHandler('node')
      ->resetCache();

    $counter = $this->container->get('badcamp_sponsors.coupon_counter');
    $this->assertSame(0, $counter->countBySponsor($sponsor_a));

    $this->assertTrue(
      $sponsor_a->access('delete', $manager, TRUE)->isAllowed(),
      'Deleting is allowed once no coupon references the sponsor.'
    );

    // The actual deletion runs clean.
    $this->hardDelete($sponsor_a);
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $this->assertNull($storage->load($sponsor_a->id()), 'The sponsor is gone.');
  }

}
