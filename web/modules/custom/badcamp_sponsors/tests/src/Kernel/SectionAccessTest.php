<?php

declare(strict_types=1);

namespace Drupal\Tests\badcamp_sponsors\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests access to the /sponsors section pages and CSV routes (AC 6).
 */
#[Group('badcamp_sponsors')]
class SectionAccessTest extends KernelTestBase {

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
    $this->container->get('router.builder')->rebuild();
    // User 1 bypasses node access; create it first so every account
    // the assertions is a regular user.
    $this->drupalCreateUser();
  }

  /**
   * Tests the shipped routes, their paths and requirements.
   */
  public function testSectionRoutes(): void {
    $provider = $this->container->get('router.route_provider');
    $expected = [
      'view.sponsors.page_1' => '/sponsors',
      'view.campaigns.page_1' => '/sponsors/campaigns',
      'view.coupons.page_1' => '/sponsors/coupons',
    ];
    foreach ($expected as $route_name => $path) {
      $route = $provider->getRouteByName($route_name);
      $this->assertSame($path, $route->getPath(), "$route_name path.");
      // The section pages render in the administration theme.
      $this->assertTrue(
        (bool) $route->getOption('_admin_route'),
        "$route_name is an admin route."
      );
    }
    $this->assertSame(
      'access sponsors section',
      $provider->getRouteByName('view.sponsors.page_1')->getRequirement('_permission')
    );
    $this->assertSame(
      'access sponsors section',
      $provider->getRouteByName('view.campaigns.page_1')->getRequirement('_permission')
    );
    // The coupons page requires the coupon-view permission as well.
    $this->assertSame(
      'access sponsors section, view coupon content',
      $provider->getRouteByName('view.coupons.page_1')->getRequirement('_permission')
    );
  }

  /**
   * Tests that each CSV route exists and carries the same requirements.
   */
  public function testCsvRoutesSharePermissions(): void {
    $provider = $this->container->get('router.route_provider');
    $expected = [
      '/sponsors/export.csv' => 'view.sponsors.page_1',
      '/sponsors/campaigns/export.csv' => 'view.campaigns.page_1',
      '/sponsors/coupons/export.csv' => 'view.coupons.page_1',
    ];
    foreach ($expected as $csv_path => $page_route_name) {
      $found = $provider->getRoutesByPattern($csv_path);
      $this->assertCount(1, $found, "Exactly one route for $csv_path.");
      $csv_route = NULL;
      foreach ($found as $route) {
        $csv_route = $route;
      }
      $page_requirement = $provider->getRouteByName($page_route_name)
        ->getRequirement('_permission');
      $this->assertSame(
        $page_requirement,
        $csv_route->getRequirement('_permission'),
        "The CSV route for $csv_path requires the same permissions as its page."
      );
    }
  }

  /**
   * Tests route access for a sponsorship manager and an ordinary user.
   */
  public function testSectionRouteAccess(): void {
    $access_manager = $this->container->get('access_manager');

    $plain = $this->drupalCreateUser(['access content']);
    $section_manager = $this->drupalCreateUser([
      'access content',
      'access sponsors section',
    ]);
    $full_manager = $this->drupalCreateUser([
      'access content',
      'access sponsors section',
      'view coupon content',
    ]);

    // Users without the section permission get the 403-equivalent result.
    foreach (['view.sponsors.page_1', 'view.campaigns.page_1', 'view.coupons.page_1'] as $route_name) {
      $this->assertFalse(
        $access_manager->checkNamedRoute($route_name, [], $plain),
        "$route_name is denied to a user without the section permission."
      );
    }

    // The section permission grants the sponsors and campaigns pages.
    $this->assertTrue($access_manager->checkNamedRoute('view.sponsors.page_1', [], $section_manager));
    $this->assertTrue($access_manager->checkNamedRoute('view.campaigns.page_1', [], $section_manager));

    // ...but not the coupons page, which also needs the coupon permission.
    $this->assertFalse(
      $access_manager->checkNamedRoute('view.coupons.page_1', [], $section_manager),
      'The coupons page also requires the coupon-view permission.'
    );
    $this->assertTrue($access_manager->checkNamedRoute('view.coupons.page_1', [], $full_manager));
  }

  /**
   * Tests that the shipped sponsorship_manager role grants the section.
   */
  public function testShippedRoleGrantsSection(): void {
    $access_manager = $this->container->get('access_manager');
    $user = $this->drupalCreateUser(['access content']);
    $user->addRole('sponsorship_manager');
    $user->save();

    foreach (['view.sponsors.page_1', 'view.campaigns.page_1', 'view.coupons.page_1'] as $route_name) {
      $this->assertTrue(
        $access_manager->checkNamedRoute($route_name, [], $user),
        "The shipped sponsorship_manager role grants $route_name."
      );
    }
  }

}
