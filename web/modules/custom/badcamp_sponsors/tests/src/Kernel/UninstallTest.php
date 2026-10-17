<?php

declare(strict_types=1);

namespace Drupal\Tests\badcamp_sponsors\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that uninstalling the module leaves nothing behind (AC 10).
 */
#[Group('badcamp_sponsors')]
class UninstallTest extends KernelTestBase {

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
    $this->installConfig(['user']);
    // Trash must be told about node before the node entity schema exists,
    // otherwise the 'deleted' base field is missing its column.
    $this->config('trash.settings')
      ->set('enabled_entity_types', ['node' => []])
      ->save();
    $this->container->get('module_installer')->install(['badcamp_sponsors']);
    $this->installSchema('node', 'node_access');

    $sponsor = $this->createSponsor('Acme Corp');
    $campaign = $this->createCampaign('Fall Blast');
    $this->createCoupon('SAVE20', $sponsor, $campaign);
    // A second coupon, then put in trash: uninstall must purge it too.
    $trashed = $this->createCoupon('SAVE21', $sponsor, $campaign);
    $trashed->delete();

    // Sanity: fixtures exist, otherwise the assertions below would pass
    // vacuously.
    $this->assertCount(
      1,
      $this->container->get('entity_type.manager')->getStorage('node')->loadByProperties(['type' => 'coupon']),
      'Sanity: a live coupon exists before uninstall.'
    );
    $this->assertNotEmpty($this->getAliases('/node/' . $sponsor->id()), 'Sanity: the sponsor alias exists before uninstall.');
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
   * Returns all aliases registered for a node system path.
   *
   * @return string[]
   *   The alias strings.
   */
  protected function getAliases(string $path): array {
    $aliases = $this->container->get('entity_type.manager')
      ->getStorage('path_alias')
      ->loadByProperties(['path' => $path]);
    return array_map(
      static fn($alias): string => $alias->getAlias(),
      $aliases
    );
  }

  /**
   * Tests the state after uninstalling the module.
   */
  public function testUninstallRemovesEverything(): void {
    $database = $this->container->get('database');
    $node_ids_before = $database->select('node_field_data', 'n')
      ->fields('n', ['nid'])
      ->condition('n.type', ['sponsor', 'campaign', 'coupon'], 'IN')
      ->execute()
      ->fetchCol();
    $this->assertNotEmpty($node_ids_before, 'Sanity: module nodes exist before uninstall.');

    $this->container->get('module_installer')->uninstall(['badcamp_sponsors']);

    // No nodes remain — the raw table check also sees the trashed coupon,
    // whose soft-deleted row survives in node_field_data.
    $count = (int) $database->select('node_field_data', 'n')
      ->condition('n.type', ['sponsor', 'campaign', 'coupon'], 'IN')
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertSame(0, $count, 'No sponsor, campaign or coupon nodes remain (including trashed ones).');

    // None of the module's config remains.
    $config_storage = $this->container->get('config.storage');
    $names = [
      'node.type.sponsor',
      'node.type.campaign',
      'node.type.coupon',
      'views.view.sponsors',
      'views.view.campaigns',
      'views.view.coupons',
      'pathauto.pattern.sponsor',
      'pathauto.pattern.campaign',
      'user.role.sponsorship_manager',
      'core.base_field_override.node.coupon.promote',
      'simple_sitemap.bundle_settings.default.node.sponsor',
      'simple_sitemap.bundle_settings.default.node.campaign',
      'simple_sitemap.bundle_settings.default.node.coupon',
    ];
    foreach (['field_sponsor_website', 'field_sponsor_logo'] as $field) {
      $names[] = "field.storage.node.$field";
      $names[] = "field.field.node.sponsor.$field";
    }
    foreach ([
      'field_coupon_code',
      'field_coupon_sponsor',
      'field_coupon_campaign',
      'field_coupon_date_issued',
      'field_coupon_redeemed',
      'field_coupon_redeemed_date',
    ] as $field) {
      $names[] = "field.storage.node.$field";
      $names[] = "field.field.node.coupon.$field";
    }
    foreach ($names as $name) {
      $this->assertFalse(
        $config_storage->exists($name),
        "$name is removed on uninstall."
      );
    }
    $this->assertSame(
      [],
      $config_storage->listAll('badcamp_sponsors.'),
      'No module-owned simple config remains.'
    );

    // None of the module's field tables remain (data and revision tables).
    $leftovers = array_filter(
      $database->schema()->findTables('node\_%'),
      static fn(string $table): bool => str_contains($table, 'field_coupon') || str_contains($table, 'field_sponsor')
    );
    $this->assertSame([], $leftovers, 'No module field tables remain.');

    // None of the module's aliases remain.
    $aliases = $this->container->get('entity_type.manager')
      ->getStorage('path_alias')
      ->loadMultiple();
    foreach ($aliases as $alias) {
      $alias_string = $alias->getAlias();
      $this->assertStringStartsNotWith('/sponsor/', $alias_string, 'No sponsor aliases remain.');
      $this->assertStringStartsNotWith('/campaign/', $alias_string, 'No campaign aliases remain.');
      $this->assertNotContains($alias->getPath(), array_map(
        static fn(int $id): string => "/node/$id",
        $node_ids_before
      ), 'No aliases point at the deleted nodes.');
    }

    // Dependencies stay enabled: uninstalling a module does not uninstall
    // its dependencies.
    $handler = $this->container->get('module_handler');
    $this->assertTrue($handler->moduleExists('views_data_export'), 'views_data_export stays enabled.');
    $this->assertTrue($handler->moduleExists('csv_serialization'), 'csv_serialization stays enabled.');
  }

  /**
   * Tests that the module can be enabled again after uninstalling it.
   */
  public function testReEnablingWorks(): void {
    $this->container->get('module_installer')->uninstall(['badcamp_sponsors']);
    $this->assertTrue(
      $this->container->get('module_installer')->install(['badcamp_sponsors']),
      'Re-enabling the module works.'
    );
    $this->assertNotNull(NodeType::load('sponsor'), 'The content types are back after re-enabling.');
  }

}
