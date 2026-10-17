<?php

declare(strict_types=1);

namespace Drupal\Tests\badcamp_sponsors\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\pathauto\Entity\PathautoPattern;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that installing the module creates everything it ships (AC 1).
 *
 * The install itself is the `drush en` equivalent: the module is installed
 * through the module installer, like `drush en badcamp_sponsors` does.
 */
#[Group('badcamp_sponsors')]
class InstallTest extends KernelTestBase {

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
    // Installing the module creates the dependency schemas (node_access
    // included) through the module installer, exactly like `drush en`.
    $this->container->get('module_installer')->install(['badcamp_sponsors']);
  }

  /**
   * Tests that the module and its hard dependencies end up enabled.
   */
  public function testDependenciesAreEnabled(): void {
    $handler = $this->container->get('module_handler');
    $this->assertTrue($handler->moduleExists('badcamp_sponsors'));
    foreach ([
      'node',
      'views_data_export',
      'csv_serialization',
      'pathauto',
      'simple_sitemap',
      // Field storage config shipped by the module needs these providers.
      'link',
      'datetime',
      'image',
    ] as $dependency) {
      $this->assertTrue($handler->moduleExists($dependency), "$dependency is enabled.");
    }
  }

  /**
   * Tests that the three content types exist after install.
   */
  public function testContentTypesAreCreated(): void {
    $types = NodeType::loadMultiple();
    foreach (['sponsor', 'campaign', 'coupon'] as $type) {
      $this->assertArrayHasKey($type, $types, "The $type content type exists.");
    }
  }

  /**
   * Tests the field config of the three bundles.
   */
  public function testFieldsAreCreated(): void {
    $expected = [
      'node.sponsor.field_sponsor_website' => ['type' => 'link', 'required' => FALSE],
      'node.sponsor.field_sponsor_logo' => ['type' => 'image', 'required' => FALSE],
      'node.coupon.field_coupon_code' => ['type' => 'string', 'required' => TRUE],
      'node.coupon.field_coupon_sponsor' => [
        'type' => 'entity_reference',
        'required' => TRUE,
        'target_bundles' => ['sponsor'],
      ],
      'node.coupon.field_coupon_campaign' => [
        'type' => 'entity_reference',
        'required' => TRUE,
        'target_bundles' => ['campaign'],
      ],
      'node.coupon.field_coupon_date_issued' => ['type' => 'datetime', 'required' => TRUE],
      'node.coupon.field_coupon_redeemed' => ['type' => 'boolean', 'required' => FALSE],
      'node.coupon.field_coupon_redeemed_date' => ['type' => 'datetime', 'required' => FALSE],
    ];
    $storage = $this->container->get('entity_type.manager')->getStorage('field_config');
    foreach ($expected as $id => $settings) {
      /** @var \Drupal\field\FieldConfigInterface $field */
      $field = $storage->load($id);
      $this->assertNotNull($field, "Field config $id exists.");
      $this->assertSame($settings['type'], $field->getType(), "$id has type {$settings['type']}.");
      $this->assertSame($settings['required'], $field->isRequired(), "$id required flag.");
      if (isset($settings['target_bundles'])) {
        $handler_settings = $field->getSetting('handler_settings');
        $this->assertSame(
          $settings['target_bundles'],
          array_values($handler_settings['target_bundles'] ?? []),
          "$id references exactly the expected bundles."
        );
        $this->assertSame('node', $field->getSetting('target_type'), "$id targets nodes.");
      }
    }
  }

  /**
   * Tests the three views, their page displays and CSV export displays (AC 7).
   */
  public function testViewsAreCreatedWithCsvExports(): void {
    $expected = [
      'sponsors' => '/sponsors',
      'campaigns' => '/sponsors/campaigns',
      'coupons' => '/sponsors/coupons',
    ];
    foreach ($expected as $view_id => $path) {
      $view = View::load($view_id);
      $this->assertNotNull($view, "The $view_id view exists.");
      $displays = $view->get('display');

      $page = $displays['page_1'] ?? NULL;
      $this->assertNotNull($page, "$view_id has a page_1 display.");
      $this->assertSame('page', $page['display_plugin'], "$view_id page_1 is a page display.");
      $this->assertSame($path, $page['display_options']['path'], "$view_id page_1 path.");
      $this->assertTrue(
        !empty($page['display_options']['use_admin_theme']),
        "$view_id page_1 uses the administration theme."
      );

      $exports = array_filter(
        $displays,
        static fn(array $display): bool => $display['display_plugin'] === 'data_export'
      );
      $this->assertCount(1, $exports, "$view_id has exactly one CSV export display.");
      $export = reset($exports);
      $this->assertSame(
        $path . '/export.csv',
        $export['display_options']['path'],
        "$view_id CSV export path."
      );
    }
  }

  /**
   * Tests the two Pathauto patterns (and that there is no coupon pattern).
   */
  public function testPathautoPatternsAreCreated(): void {
    $ids = array_keys(PathautoPattern::loadMultiple());
    sort($ids);
    $this->assertSame(['campaign', 'sponsor'], $ids, 'Exactly two patterns ship: sponsor and campaign.');

    $checks = [
      'sponsor' => ['/sponsor/[node:title]', 'sponsor'],
      'campaign' => ['/campaign/[node:title]', 'campaign'],
    ];
    foreach ($checks as $id => [$pattern_string, $bundle]) {
      $pattern = PathautoPattern::load($id);
      $this->assertNotNull($pattern, "The $id pathauto pattern exists.");
      $this->assertSame($pattern_string, $pattern->getPattern(), "$id pattern string.");
      $this->assertSame('canonical_entities:node', $pattern->getType(), "$id applies to node URLs.");
      $bundles = [];
      foreach ($pattern->getSelectionConditions() as $condition) {
        if ($condition->getPluginId() === 'node_type') {
          $bundles = array_merge($bundles, $condition->getConfiguration()['bundles'] ?? []);
        }
      }
      $this->assertContains($bundle, $bundles, "$id selects the $bundle bundle.");
    }
  }

  /**
   * Tests the sponsorship_manager role and its exact permission set.
   */
  public function testSponsorshipManagerRoleIsCreated(): void {
    $role = $this->config('user.role.sponsorship_manager');
    $this->assertFalse($role->isNew(), 'The sponsorship_manager role exists.');
    $this->assertFalse((bool) $role->get('is_admin'), 'The role is not an admin role.');
    $permissions = $role->get('permissions') ?? [];
    sort($permissions);
    $this->assertSame([
      'access content',
      'access content overview',
      'access sponsors section',
      'create campaign content',
      'create coupon content',
      'create sponsor content',
      'delete any campaign content',
      'delete any coupon content',
      'delete any sponsor content',
      'edit any campaign content',
      'edit any coupon content',
      'edit any sponsor content',
      'view coupon content',
    ], $permissions);
  }

  /**
   * Tests the admin menu links shipped by the module.
   */
  public function testMenuLinksAreCreated(): void {
    $manager = $this->container->get('plugin.manager.menu.link');
    $this->assertTrue($manager->hasDefinition('badcamp_sponsors.sponsors'));
    $top = $manager->getDefinition('badcamp_sponsors.sponsors');
    $this->assertSame('view.sponsors.page_1', $top['route_name']);

    foreach (['campaigns', 'coupons'] as $child) {
      $this->assertTrue($manager->hasDefinition("badcamp_sponsors.$child"));
      $definition = $manager->getDefinition("badcamp_sponsors.$child");
      $this->assertSame('badcamp_sponsors.sponsors', $definition['parent'] ?? NULL, "The $child link is a child of the Sponsors item.");
      $this->assertSame("view.$child.page_1", $definition['route_name']);
    }
  }

  /**
   * Tests coupon privacy-related bundle settings (from the issue).
   */
  public function testCouponBundleSettings(): void {
    // Sitemap exclusion for all three bundles: simple_sitemap 4.x config
    // names carry the variant segment ('default'), and 'index' must be FALSE.
    foreach (['sponsor', 'campaign', 'coupon'] as $bundle) {
      $name = "simple_sitemap.bundle_settings.default.node.$bundle";
      $this->assertTrue(
        $this->container->get('config.storage')->exists($name),
        "$name exists."
      );
      $this->assertFalse(
        (bool) $this->config($name)->get('index'),
        "The $bundle bundle is excluded from the sitemap."
      );
    }
    // Coupons are never promoted: the promote override must ship as config.
    $this->assertTrue(
      $this->container->get('config.storage')->exists('core.base_field_override.node.coupon.promote'),
      'The coupon promote override exists.'
    );
    // No metatag field on coupons.
    $fields = $this->container->get('entity_field.manager')
      ->getFieldDefinitions('node', 'coupon');
    $this->assertArrayNotHasKey('field_metatag', $fields);
    foreach ($fields as $name => $definition) {
      $this->assertNotSame('metatag', $definition->getType(), "No metatag field on coupon ($name).");
    }
  }

  /**
   * Tests that the three types integrate with the content overview (AC 2).
   */
  public function testContentOverviewIntegration(): void {
    // The core content view is optional config of the node module; it gets
    // imported because views is enabled while node is installed.
    $content = View::load('content');
    $this->assertNotNull($content, 'The core content view exists.');
    // Views stores shared options on the default display; page_1 inherits
    // them at runtime. Core's shipped content view defines the type filter
    // on the default display only.
    $filters = $content->get('display')['default']['display_options']['filters'] ?? [];
    $type_filter = $filters['type'] ?? NULL;
    $this->assertNotNull($type_filter, 'The content view exposes a type filter.');
    $this->assertSame('bundle', $type_filter['plugin_id']);
    // Not restricted to a subset of bundles: every node type, including the
    // three shipped by the module, is offered.
    $this->assertSame([], $type_filter['value']);
    $bundles = $this->container->get('entity_type.bundle.info')
      ->getBundleInfo('node');
    foreach (['sponsor', 'campaign', 'coupon'] as $type) {
      $this->assertArrayHasKey($type, $bundles, "The $type type is in the node type list.");
    }
  }

}
