<?php

declare(strict_types=1);

namespace Drupal\Tests\badcamp_sponsors\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests entity CRUD, generated coupon titles, and coupon form display.
 *
 * Covers all three node types, the generated coupon title (AC 2), and the
 * coupon form-display contracts: hidden title widget, read-only Coupon ID.
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
    // System config provides the date formats the token system needs when
    // pathauto runs on node save.
    $this->installConfig(['user', 'system']);
    $this->container->get('module_installer')->install(['badcamp_sponsors']);
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

  /**
   * Tests that the coupon edit form shows the saved Coupon ID read-only.
   *
   * Issue field spec: "shown in lists and on the edit form once saved; never
   * editable".
   *
   * Pinned mechanism: a badcamp_sponsors_coupon_id extra field on the
   * node.coupon default form display (a shown component, never hidden),
   * populated by hook_form_NODE_FORM_alter for saved coupons as a
   * #type => 'item' element whose #markup is the nid -- an 'item' is
   * #input FALSE (rendered markup), so no editor input and no widget
   * writing to the node.
   */
  public function testCouponIdShownReadOnlyOnEditForm(): void {
    $sponsor = $this->createSponsor('Acme Corp');
    $campaign = $this->createCampaign('Fall Blast');
    $coupon = $this->createCoupon('SAVE20', $sponsor, $campaign);

    // Build the real coupon edit form for the saved node (entity-form level;
    // no browser).
    $form = $this->container->get('entity.form_builder')->getForm($coupon, 'default');

    // The Coupon ID is present on the built form and shows the saved ID.
    $this->assertArrayHasKey(
      'badcamp_sponsors_coupon_id',
      $form,
      'The Coupon ID element is present on the built coupon edit form.'
    );
    $element = $form['badcamp_sponsors_coupon_id'];
    $this->assertSame(
      'item',
      $element['#type'] ?? NULL,
      'The Coupon ID is a read-only #type => item element, never an editable widget.'
    );
    $this->assertSame(
      'Coupon ID',
      (string) ($element['#title'] ?? ''),
      'The Coupon ID element is labeled for the editor.'
    );
    $this->assertStringContainsString(
      (string) $coupon->id(),
      (string) ($element['#markup'] ?? ''),
      'The Coupon ID element renders the saved node ID.'
    );

    // The form display carries the extra field as a shown component, not
    // hidden: the config contract behind the built form.
    $form_display = EntityFormDisplay::load('node.coupon.default');
    $this->assertNotNull($form_display, 'The coupon default form display exists.');
    $this->assertIsArray(
      $form_display->getComponent('badcamp_sponsors_coupon_id'),
      'The Coupon ID extra field is a shown component of the coupon default form display.'
    );
    $this->assertArrayNotHasKey(
      'badcamp_sponsors_coupon_id',
      $form_display->get('hidden') ?? [],
      'The Coupon ID component is not hidden on the edit form.'
    );
  }

}
