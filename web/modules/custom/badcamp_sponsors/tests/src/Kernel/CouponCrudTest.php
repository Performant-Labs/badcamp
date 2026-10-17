<?php

declare(strict_types=1);

namespace Drupal\Tests\badcamp_sponsors\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests entity-API CRUD on the three types and the generated title (AC 2).
 */
#[Group('badcamp_sponsors')]
class CouponCrudTest extends KernelTestBase {

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
      // The module must replace this placeholder on save.
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
   * Tests create/edit/delete of all three types through the entity API.
   */
  public function testCreateEditDelete(): void {
    // Sponsors and campaigns keep their editor-provided titles.
    $sponsor = $this->createSponsor('Acme Corp');
    $this->assertSame('Acme Corp', $sponsor->getTitle());
    $campaign = $this->createCampaign('Fall Blast');
    $this->assertSame('Fall Blast', $campaign->getTitle());

    // Coupon: title is generated on save; the code is stored trimmed.
    $coupon = $this->createCoupon('  SAVE20  ', $sponsor, $campaign);
    /** @var \Drupal\node\NodeStorageInterface $storage */
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache([$coupon->id()]);
    $coupon = $storage->load($coupon->id());
    $this->assertSame('SAVE20', $coupon->get('field_coupon_code')->value, 'The coupon code is stored trimmed.');
    $this->assertSame(
      'SAVE20 — Acme Corp / Fall Blast',
      $coupon->getTitle(),
      'The coupon title is generated from code, sponsor and campaign.'
    );
    // Coupons are never promoted to the front page.
    $this->assertFalse($coupon->isPromoted(), 'Coupons are not promoted.');
    // Date issued defaults to now.
    $date_issued = $coupon->get('field_coupon_date_issued')->value;
    $this->assertNotNull($date_issued);
    $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $date_issued);

    // Editing the code regenerates the title.
    $coupon->set('field_coupon_code', '  SAVE30  ');
    $coupon->save();
    $storage->resetCache([$coupon->id()]);
    $coupon = $storage->load($coupon->id());
    $this->assertSame('SAVE30', $coupon->get('field_coupon_code')->value);
    $this->assertSame('SAVE30 — Acme Corp / Fall Blast', $coupon->getTitle());

    // Deleting each type through the entity API works.
    $coupon->delete();
    $this->assertNull($storage->load($coupon->id()));
    $campaign->delete();
    $this->assertNull($storage->load($campaign->id()));
    $sponsor->delete();
    $this->assertNull($storage->load($sponsor->id()));
  }

  /**
   * Tests that the title widget is hidden from the coupon form.
   */
  public function testCouponTitleWidgetIsHidden(): void {
    $form_display = EntityFormDisplay::load('node.coupon.default');
    $this->assertNotNull($form_display, 'The coupon default form display exists.');
    $this->assertNull(
      $form_display->getComponent('title'),
      'The title widget is hidden from the coupon form.'
    );
  }

}
