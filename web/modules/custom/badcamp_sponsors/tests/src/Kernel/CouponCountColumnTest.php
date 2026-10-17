<?php

declare(strict_types=1);

namespace Drupal\Tests\badcamp_sponsors\Kernel;

use Drupal\badcamp_sponsors\Plugin\views\field\CouponCount;
use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\views\Plugin\views\display\Page;
use Drupal\views\Plugin\views\field\Broken;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;

/**
 * Regression tests for the third AC 9 walkthrough defect (fix round 3).
 *
 * The "Coupons" count column on the sponsors and campaigns views rendered
 * EMPTY: both views ship the field as (table 'node_field_data', field
 * 'badcamp_sponsors_coupon_count'), and ViewsHandlerManager resolves a
 * config-referenced handler through hook_views_data
 * (ViewsHandlerManager.php:92 getHandler() reads $data[$field]['field']).
 * The module shipped no views data, so the handler resolved to the 'broken'
 * plugin (ViewsHandlerManager.php:140), whose render() is the inherited
 * FieldPluginBase::render() — getValue() on a handler with no real field
 * returns NULL, so every cell rendered as '' (FieldPluginBase.php:1181).
 * The plugin itself and its counter service were never broken — the
 * delete-block rule uses the service and its tests pass; only the
 * resolution was missing, and no test rendered either view.
 *
 * The fix is hook_views_data in badcamp_sponsors.views.inc (auto-loaded
 * when views collects data via views_hook_info()'s 'views' group,
 * views.module:162): $data['node_field_data']['badcamp_sponsors_coupon_count']
 * with title 'Coupons' and field => ['id' => 'badcamp_sponsors_coupon_count'].
 * These tests pin that contract at the layer that failed: the handler the
 * views actually resolve, and the rendered page tables.
 */
#[Group('badcamp_sponsors')]
class CouponCountColumnTest extends KernelTestBase {

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
    $this->container->get('router.builder')->rebuild();
    // User 1 bypasses node access; create it first so every account used in
    // the assertions is a regular user.
    $this->drupalCreateUser();

    // Two sponsors: one referenced by both seeded coupons, one referenced
    // by none (the zero-count row).
    $counted_sponsor = $this->createSponsor('Counted Sponsor');
    $this->createSponsor('Empty Sponsor');
    $campaign = $this->createCampaign('Counted Campaign');

    // Two coupons referencing the SAME sponsor and campaign, one live and
    // one trashed: the column must show 2 — the trash-aware number the
    // delete-block rule uses — not the live-only 1.
    $live = $this->createCoupon('COUNTLIVE', $counted_sponsor, $campaign);
    $this->assertNotEmpty($live->id());
    $trashed = $this->createCoupon('COUNTTRASH', $counted_sponsor, $campaign);
    // With trash enabled for node and the trash context 'active' (the
    // default), delete() soft-deletes.
    $trashed->delete();
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
   * Returns a user carrying the shipped sponsorship_manager role.
   */
  protected function sponsorshipManager(): AccountInterface {
    $manager = $this->drupalCreateUser(['access content']);
    $manager->addRole('sponsorship_manager');
    $manager->save();
    return $manager;
  }

  /**
   * Renders a view's page display as an account, on the page controller path.
   *
   * This is ViewPageController::handle()'s exact build:
   * Page::buildBasicRenderable() -> '#type' => 'view' render element with
   * '#embed' => FALSE, rendered through the renderer, so the element's
   * pre-render callback runs the view via executeDisplay()
   * (views/src/Element/View.php:81) — the same build the live page used.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to render as (via account_switcher).
   * @param string $view_id
   *   The view machine name. Its page display must be 'page_1'.
   *
   * @return string
   *   The rendered page HTML.
   */
  protected function renderViewPageAs(AccountInterface $account, string $view_id): string {
    $route = $this->container->get('router.route_provider')
      ->getRouteByName("view.$view_id.page_1");
    $build = Page::buildBasicRenderable($view_id, 'page_1', [], $route);

    $switcher = $this->container->get('account_switcher');
    $switcher->switchTo($account);
    try {
      return (string) $this->container->get('renderer')->renderRoot($build);
    }
    finally {
      $switcher->switchBack();
    }
  }

  /**
   * Returns the trimmed text of every rendered "Coupons" count cell.
   *
   * Both views are table-styled; the table preprocessor gives each data
   * cell the class views-field-coupon-count (field id 'coupon_count'
   * through Html::cleanCssIdentifier, ViewsThemeHooks.php:676 +
   * views-view-table.html.twig), so that class identifies the count column
   * no matter what the other columns render.
   *
   * @param string $html
   *   A rendered page.
   *
   * @return string[]
   *   The count cell texts, one per row, in row order.
   */
  protected function couponCountCells(string $html): array {
    preg_match_all(
      '#<td[^>]*views-field-coupon-count[^>]*>(.*?)</td>#s',
      $html,
      $matches
    );
    $cells = [];
    foreach ($matches[1] as $cell) {
      $cells[] = trim(html_entity_decode(strip_tags($cell)));
    }
    return $cells;
  }

  /**
   * Asserts the count column exists in a rendered table (harness guard).
   *
   * The cell class derives from the configured field id, not from the
   * handler, so the column markup is present even while the handler is
   * broken — proving that a missing '2' below is the broken handler, not a
   * harness that failed to find the column.
   */
  protected function assertCountColumnPresent(string $html): void {
    $this->assertStringContainsString(
      'views-field-coupon-count',
      $html,
      'The rendered table carries the coupon-count column markup.'
    );
  }

  /**
   * Tests that the sponsors row counts live AND trashed coupons.
   */
  public function testSponsorsRowShowsCouponCountIncludingTrashed(): void {
    $html = $this->renderViewPageAs($this->sponsorshipManager(), 'sponsors');
    $this->assertCountColumnPresent($html);
    $this->assertContains(
      '2',
      $this->couponCountCells($html),
      'The sponsors view counts the live and the trashed coupon for the referencing sponsor.'
    );
  }

  /**
   * Tests that the campaigns row counts live AND trashed coupons.
   */
  public function testCampaignsRowShowsCouponCountIncludingTrashed(): void {
    $html = $this->renderViewPageAs($this->sponsorshipManager(), 'campaigns');
    $this->assertCountColumnPresent($html);
    $this->assertContains(
      '2',
      $this->couponCountCells($html),
      'The campaigns view counts the live and the trashed coupon for the referencing campaign.'
    );
  }

  /**
   * Tests that the views resolve the coupon_count handler to the plugin.
   *
   * Pins the fix's resolution seam: without hook_views_data for the
   * (node_field_data, badcamp_sponsors_coupon_count) pair,
   * ViewsHandlerManager::getHandler() falls through to the 'broken' plugin,
   * and the plugin class — attribute-discovered, service-backed, otherwise
   * functional — never runs.
   */
  public function testCouponCountHandlerResolvesToCouponCountPlugin(): void {
    foreach (['sponsors', 'campaigns'] as $view_id) {
      $view = Views::getView($view_id);
      $view->setDisplay('page_1');
      $view->initHandlers();
      $handler = $view->field['coupon_count'];
      $this->assertNotInstanceOf(
        Broken::class,
        $handler,
        "The $view_id view resolves the coupon_count handler instead of falling back to Broken."
      );
      $this->assertInstanceOf(
        CouponCount::class,
        $handler,
        "The $view_id view resolves the coupon_count handler to the module's CouponCount plugin."
      );
    }
  }

  /**
   * Tests that a sponsor with zero coupons renders a 0, not an empty cell.
   */
  public function testSponsorWithZeroCouponsRendersZeroCount(): void {
    $html = $this->renderViewPageAs($this->sponsorshipManager(), 'sponsors');
    $this->assertCountColumnPresent($html);
    $this->assertContains(
      '0',
      $this->couponCountCells($html),
      'A sponsor with no coupons shows a 0 count, not an empty cell.'
    );
  }

}
