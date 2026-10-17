<?php

declare(strict_types=1);

namespace Drupal\Tests\badcamp_sponsors\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\views\Plugin\views\display\Page;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;

/**
 * Regression tests for the AC 9 walkthrough defect (post-merge fix loop).
 *
 * The coupons page rendered EMPTY (200, no view element) for every non-admin
 * user: the display's access shipped as the two-permission string
 * 'access sponsors section,view coupon content', but views' Permission
 * access plugin checks a SINGLE permission
 * (user/src/Plugin/views/access/Permission.php:89 hasPermission()) while only
 * the route layer AND-splits commas (core's PermissionAccessCheck). The route
 * check passed for managers, the display-level check failed for everyone.
 *
 * The fix is an in-module views access plugin whose access() is the AND of
 * the two permissions and whose alterRouteDefinition() keeps the unspaced
 * _permission string. These tests pin that contract at the layer that failed:
 * the rendered page, as real non-admin accounts.
 */
#[Group('badcamp_sponsors')]
class CouponsViewRenderAccessTest extends KernelTestBase {

  use UserCreationTrait {
    createUser as drupalCreateUser;
  }

  /**
   * The coupon code seeded in setUp(); the listing must show it to managers.
   */
  private const COUPON_CODE = 'WTSAVE20';

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
    $this->container->get('router.builder')->rebuild();
    // User 1 bypasses node access; create it first so every account used in
    // the assertions is a regular user.
    $this->drupalCreateUser();

    // Seed one coupon so the coupons listing has content to render.
    $sponsor = Node::create([
      'type' => 'sponsor',
      'title' => 'Walkthrough Sponsor',
      'status' => 1,
    ]);
    $sponsor->save();
    $campaign = Node::create([
      'type' => 'campaign',
      'title' => 'Walkthrough Campaign',
      'status' => 1,
    ]);
    $campaign->save();
    $coupon = Node::create([
      'type' => 'coupon',
      'title' => 'Placeholder title',
      'status' => 1,
      'field_coupon_code' => self::COUPON_CODE,
      'field_coupon_sponsor' => ['target_id' => $sponsor->id()],
      'field_coupon_campaign' => ['target_id' => $campaign->id()],
    ]);
    $coupon->save();
  }

  /**
   * Renders a view's page display as an account, on the page controller path.
   *
   * This is ViewPageController::handle()'s exact build:
   * Page::buildBasicRenderable() -> '#type' => 'view' render element with
   * '#embed' => FALSE, rendered through the renderer, so the element's
   * pre-render callback runs (views/src/Element/View.php:64) — the display
   * access gate that failed in the walkthrough. Inside the gate, that
   * callback runs the view via executeDisplay() (Element/View.php:81).
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
   * Returns the data_export display id of a view.
   */
  protected function getExportDisplayId(string $view_id): string {
    $displays = Views::getView($view_id)->storage->get('display');
    foreach ($displays as $display_id => $display) {
      if ($display['display_plugin'] === 'data_export') {
        return $display_id;
      }
    }
    $this->fail("The $view_id view has no data_export display.");
  }

  /**
   * Tests that a manager sees the coupon codes on /sponsors/coupons.
   *
   * This is the test the shipped defect would have failed: the page rendered
   * empty (200, no view element) for the manager because the display-level
   * permission check compared a single-permission lookup against the
   * two-permission AND string.
   */
  public function testManagerSeesCouponCodesOnCouponsPage(): void {
    $manager = $this->drupalCreateUser(['access content']);
    $manager->addRole('sponsorship_manager');
    $manager->save();

    // The display-level access grant the page render consults. The shipped
    // role carries both permissions, so the AND must pass.
    $view = Views::getView('coupons');
    $this->assertTrue(
      $view->access('page_1', $manager),
      'The coupons display grants access to a sponsorship manager.'
    );

    $html = $this->renderViewPageAs($manager, 'coupons');
    $this->assertStringContainsString(
      'views-element-container',
      $html,
      'The coupons page renders a view element for a sponsorship manager.'
    );
    $this->assertStringContainsString(
      self::COUPON_CODE,
      $html,
      'The coupons page shows the seeded coupon code to a sponsorship manager.'
    );
  }

  /**
   * Tests that the coupons displays use the module's AND access plugin.
   *
   * Pins the fix's config contract: the page and CSV export displays resolve
   * to the in-module plugin (id badcamp_sponsors_section_and_coupon) whose
   * access() AND-splits the two permissions, replacing core's single-
   * permission 'perm' plugin that caused the empty render.
   */
  public function testCouponsDisplaysUseSectionAndCouponAccessPlugin(): void {
    $view = Views::getView('coupons');
    foreach (['page_1', $this->getExportDisplayId('coupons')] as $display_id) {
      $view->setDisplay($display_id);
      $plugin = $view->display_handler->getPlugin('access');
      $this->assertSame(
        'badcamp_sponsors_section_and_coupon',
        $plugin->getPluginId(),
        "The coupons $display_id display uses the section-AND-coupon access plugin."
      );
    }
  }

  /**
   * Tests that a section-only user is denied the coupons page and route.
   */
  public function testSectionOnlyUserIsDeniedOnCouponsPage(): void {
    $section_only = $this->drupalCreateUser([
      'access content',
      'access sponsors section',
    ]);

    // Display-level: the AND is not satisfied.
    $view = Views::getView('coupons');
    $this->assertFalse(
      $view->access('page_1', $section_only),
      'The coupons display denies a section-only user.'
    );

    // The page build renders no view element and leaks no coupon code.
    $html = $this->renderViewPageAs($section_only, 'coupons');
    $this->assertStringNotContainsString(
      'views-element-container',
      $html,
      'A section-only user gets no view element on the coupons page.'
    );
    $this->assertStringNotContainsString(
      self::COUPON_CODE,
      $html,
      'A section-only user sees no coupon code.'
    );

    // Route-level: the unspaced pair ANDs, so the 403 behavior holds.
    $this->assertFalse(
      $this->container->get('access_manager')
        ->checkNamedRoute('view.coupons.page_1', [], $section_only),
      'The coupons route denies a section-only user.'
    );
  }

  /**
   * Tests that a coupon-only user (inverse permissions) is denied too.
   */
  public function testCouponOnlyUserIsDeniedOnCouponsPage(): void {
    $coupon_only = $this->drupalCreateUser([
      'access content',
      'view coupon content',
    ]);

    $view = Views::getView('coupons');
    $this->assertFalse(
      $view->access('page_1', $coupon_only),
      'The coupons display denies a user without the section permission.'
    );

    $html = $this->renderViewPageAs($coupon_only, 'coupons');
    $this->assertStringNotContainsString(
      'views-element-container',
      $html,
      'A coupon-only user gets no view element on the coupons page.'
    );
    $this->assertStringNotContainsString(
      self::COUPON_CODE,
      $html,
      'A coupon-only user sees no coupon code.'
    );

    $this->assertFalse(
      $this->container->get('access_manager')
        ->checkNamedRoute('view.coupons.page_1', [], $coupon_only),
      'The coupons route denies a coupon-only user.'
    );
  }

  /**
   * Tests that anonymous users are denied the coupons page and route.
   */
  public function testAnonymousIsDeniedOnCouponsPage(): void {
    $anonymous = new AnonymousUserSession();

    $view = Views::getView('coupons');
    $this->assertFalse(
      $view->access('page_1', $anonymous),
      'The coupons display denies anonymous users.'
    );

    $html = $this->renderViewPageAs($anonymous, 'coupons');
    $this->assertStringNotContainsString(
      'views-element-container',
      $html,
      'Anonymous users get no view element on the coupons page.'
    );
    $this->assertStringNotContainsString(
      self::COUPON_CODE,
      $html,
      'Anonymous users see no coupon code.'
    );

    $this->assertFalse(
      $this->container->get('access_manager')
        ->checkNamedRoute('view.coupons.page_1', [], $anonymous),
      'The coupons route denies anonymous users.'
    );
  }

  /**
   * Tests that the coupons route keeps the unspaced AND permission pair.
   *
   * Pins the fix's alterRouteDefinition() contract: the route requirement
   * must stay the byte-exact unspaced string, because core's route-layer
   * PermissionAccessCheck AND-splits on the bare comma — that is what
   * preserves the 403 behavior for under-privileged users while letting
   * managers past the router.
   */
  public function testCouponsRouteRequiresUnspacedPermissionPair(): void {
    $requirement = $this->container->get('router.route_provider')
      ->getRouteByName('view.coupons.page_1')
      ->getRequirement('_permission');
    $this->assertSame(
      'access sponsors section,view coupon content',
      $requirement,
      'The coupons route requires the unspaced two-permission AND string.'
    );
  }

  /**
   * Tests the single-perm sponsors view still renders (over-fix guard).
   */
  public function testSponsorsPageStillRendersForSectionOnlyUser(): void {
    $section_only = $this->drupalCreateUser([
      'access content',
      'access sponsors section',
    ]);

    $html = $this->renderViewPageAs($section_only, 'sponsors');
    $this->assertStringContainsString(
      'views-element-container',
      $html,
      'The sponsors page still renders a view element for a section-only user.'
    );
    $this->assertStringContainsString(
      'Walkthrough Sponsor',
      $html,
      'The sponsors page still shows the seeded sponsor to a section-only user.'
    );
  }

}
