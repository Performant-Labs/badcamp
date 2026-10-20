<?php

declare(strict_types=1);

namespace Drupal\Tests\badcamp_sponsors\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\trash\Trash;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that restore access refuses a live duplicate coupon.
 *
 * The refusal is a node access result on the 'restore' operation: for an
 * account holding trash's dynamic 'restore node entities' permission, a
 * trashed coupon whose (normalized code, sponsor, campaign) matches a live
 * coupon is forbidden with a reason naming the code. Every other restore stays
 * allowed, and unrelated operations are untouched.
 */
#[Group('badcamp_sponsors')]
class CouponRestoreAccessTest extends KernelTestBase {

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
    // User 1 bypasses node access; create it first so every account used in
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
   * Loads a soft-deleted coupon in the 'ignore' trash context.
   *
   * The access hooks read the 'deleted' base field from the passed entity, so
   * the object under test must carry it. A coupon loaded in the default
   * 'active' context would be invisible (soft-deleted), so the reload happens
   * in 'ignore'.
   */
  protected function loadTrashed(NodeInterface $coupon): NodeInterface {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $nid = (int) $coupon->id();
    /** @var \Drupal\node\NodeInterface|null $trashed */
    $trashed = $this->container->get('trash.manager')->executeInTrashContext('ignore', fn () => $storage->load($nid));
    $this->assertNotNull($trashed, 'The trashed coupon is still loadable in the ignore trash context.');
    return $trashed;
  }

  /**
   * Creates a regular user allowed to restore node entities.
   */
  protected function restorer(): UserInterface {
    return $this->drupalCreateUser(['access content', 'restore node entities']);
  }

  /**
   * Tests that a restore duplicating a live coupon's code is refused (AC 4).
   */
  public function testRestoreRefusedWhenLiveDuplicateExists(): void {
    $sponsor = $this->createSponsor('Acme Corp');
    $campaign = $this->createCampaign('Fall Blast');
    // The live coupon whose code the trashed coupon would duplicate.
    $this->createCoupon('SAVE20', $sponsor, $campaign);
    // Same normalized triple, now trashed.
    $trashed = $this->createCoupon('  SAVE20  ', $sponsor, $campaign);
    $trashed->delete();
    $trashed = $this->loadTrashed($trashed);

    $account = $this->restorer();

    $result = $trashed->access('restore', $account, TRUE);
    $this->assertTrue(
      $result->isForbidden(),
      'Restoring a coupon whose code duplicates a live coupon is forbidden.'
    );
    $reason = $result->getReason();
    $this->assertNotEmpty($reason, 'The refusal carries a non-empty reason.');
    $this->assertStringContainsString('SAVE20', (string) $reason, 'The refusal reason names the duplicate code.');

    // Positive control: the same account restores a non-duplicate coupon, so
    // the refusal is isolated to the duplicate finder, not to a missing
    // permission.
    $other = $this->createCoupon('SAVE99', $sponsor, $campaign);
    $other->delete();
    $other = $this->loadTrashed($other);
    $this->assertTrue(
      $other->access('restore', $account, TRUE)->isAllowed(),
      'The same account can restore a non-duplicate coupon.'
    );
  }

  /**
   * Tests that restores stay allowed when the triple differs or is missing.
   *
   * Four cases, all allowed for a restore-permitted account: a different code,
   * a different sponsor, a different campaign, and a coupon missing its code
   * (the hook returns neutral, so trash's own allowance stands).
   */
  public function testRestoreAllowedWhenTripleDiffersOrFieldMissing(): void {
    $sponsor_a = $this->createSponsor('Acme Corp');
    $sponsor_b = $this->createSponsor('Beta LLC');
    $campaign_a = $this->createCampaign('Fall Blast');
    $campaign_b = $this->createCampaign('Winter Sale');
    // The live coupon whose triple the other cases must NOT duplicate.
    $this->createCoupon('SAVE20', $sponsor_a, $campaign_a);

    $account = $this->restorer();

    $cases = [
      'different code' => $this->createCoupon('SAVE99', $sponsor_a, $campaign_a),
      'different sponsor' => $this->createCoupon('SAVE20', $sponsor_b, $campaign_a),
      'different campaign' => $this->createCoupon('SAVE20', $sponsor_a, $campaign_b),
      'missing code' => $this->createCoupon('', $sponsor_a, $campaign_a),
    ];
    foreach ($cases as $label => $coupon) {
      $coupon->delete();
      $trashed = $this->loadTrashed($coupon);
      $this->assertTrue(
        $trashed->access('restore', $account, TRUE)->isAllowed(),
        "Restoring a trashed coupon with a $label is allowed."
      );
    }
  }

  /**
   * Tests two trashed same-triple coupons.
   *
   * The second is refused after the first is restored (AC 6).
   */
  public function testSecondTrashedCouponRefusedAfterFirstRestored(): void {
    $sponsor = $this->createSponsor('Acme Corp');
    $campaign = $this->createCampaign('Fall Blast');
    $first = $this->createCoupon('SAVE20', $sponsor, $campaign);
    $first->delete();
    $second = $this->createCoupon('SAVE20', $sponsor, $campaign);
    $second->delete();

    $first = $this->loadTrashed($first);
    $second = $this->loadTrashed($second);

    $account = $this->restorer();

    // With no live coupon sharing the triple, both trashed coupons restore.
    $this->assertTrue(
      $first->access('restore', $account, TRUE)->isAllowed(),
      'The first trashed coupon is restorable while no live coupon shares its triple.'
    );
    $this->assertTrue(
      $second->access('restore', $account, TRUE)->isAllowed(),
      'The second trashed coupon is restorable while no live coupon shares its triple (it is never its own duplicate).'
    );

    // Restore the first; it becomes the live coupon of that triple.
    Trash::restoreEntity($first);
    // Access results are cached per entity: clear them before re-checking.
    $this->container->get('entity_type.manager')
      ->getAccessControlHandler('node')
      ->resetCache();

    $this->assertTrue(
      $second->access('restore', $account, TRUE)->isForbidden(),
      'After the first coupon is restored, restoring the second is refused.'
    );
  }

  /**
   * Tests the cacheability of the forbidden restore result (AC 7).
   */
  public function testForbiddenResultCarriesCacheability(): void {
    $sponsor = $this->createSponsor('Acme Corp');
    $campaign = $this->createCampaign('Fall Blast');
    $this->createCoupon('SAVE20', $sponsor, $campaign);
    $trashed = $this->createCoupon('SAVE20', $sponsor, $campaign);
    $trashed->delete();
    $trashed = $this->loadTrashed($trashed);

    $account = $this->restorer();
    $result = $trashed->access('restore', $account, TRUE);

    $this->assertTrue(
      $result->isForbidden(),
      'The duplicate restore is forbidden, so its cacheability can be inspected.'
    );
    $this->assertContains(
      'node_list:coupon',
      $result->getCacheTags(),
      'The forbidden result varies with the coupon list.'
    );
    $this->assertContains(
      'user.permissions',
      $result->getCacheContexts(),
      'The forbidden result varies by permission.'
    );
  }

  /**
   * Tests that unrelated operations are unaffected (AC 8).
   */
  public function testUnrelatedOperationsUnaffected(): void {
    $sponsor = $this->createSponsor('Acme Corp');
    $campaign = $this->createCampaign('Fall Blast');

    // (a) Re-issuing a code that a trashed coupon holds stays allowed: the
    // uniqueness validation only looks at live coupons.
    $trashed = $this->createCoupon('SAVE20', $sponsor, $campaign);
    $trashed->delete();
    $reissued = Node::create([
      'type' => 'coupon',
      'title' => 'Placeholder title',
      'status' => 1,
      'field_coupon_code' => 'SAVE20',
      'field_coupon_sponsor' => ['target_id' => $sponsor->id()],
      'field_coupon_campaign' => ['target_id' => $campaign->id()],
    ]);
    $has_code_violation = FALSE;
    foreach ($reissued->validate() as $violation) {
      if (str_starts_with((string) $violation->getPropertyPath(), 'field_coupon_code')) {
        $has_code_violation = TRUE;
      }
    }
    $this->assertFalse(
      $has_code_violation,
      'Re-issuing a code held by a trashed coupon raises no field_coupon_code violation.'
    );
    $reissued->save();
    $this->assertTrue($reissued->id() > 0, 'The re-issued coupon saves.');

    // (b) view/update/delete of a live coupon stay allowed for a permitted
    // account; the restore branch does not forbid them.
    $manager = $this->drupalCreateUser([
      'access content',
      'view coupon content',
      'edit any coupon content',
      'delete any coupon content',
    ]);
    $this->assertTrue(
      $reissued->access('view', $manager, TRUE)->isAllowed(),
      'Viewing a coupon stays allowed.'
    );
    $this->assertTrue(
      $reissued->access('update', $manager, TRUE)->isAllowed(),
      'Updating a coupon stays allowed.'
    );
    $this->assertTrue(
      $reissued->access('delete', $manager, TRUE)->isAllowed(),
      'Deleting a coupon stays allowed.'
    );

    // (c) sponsor/campaign delete stays governed by the counter.
    $deleter = $this->drupalCreateUser([
      'access content',
      'delete any sponsor content',
      'delete any campaign content',
    ]);
    $this->assertTrue(
      $sponsor->access('delete', $deleter, TRUE)->isForbidden(),
      'A referenced sponsor delete stays forbidden.'
    );
    $this->assertTrue(
      $campaign->access('delete', $deleter, TRUE)->isForbidden(),
      'A referenced campaign delete stays forbidden.'
    );
  }

}
