<?php

declare(strict_types=1);

namespace Drupal\Tests\badcamp_sponsors\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the coupon validation rules through $node->validate() (AC 3).
 *
 * All checks run through the entity validation API, not forms.
 */
#[Group('badcamp_sponsors')]
class CouponValidationTest extends KernelTestBase {

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
   * Creates an unsaved coupon node.
   */
  protected function buildCoupon(string $code, NodeInterface $sponsor, NodeInterface $campaign, array $overrides = []): NodeInterface {
    return Node::create($overrides + [
      'type' => 'coupon',
      'title' => 'Placeholder title',
      'status' => 1,
      'field_coupon_code' => $code,
      'field_coupon_sponsor' => ['target_id' => $sponsor->id()],
      'field_coupon_campaign' => ['target_id' => $campaign->id()],
    ]);
  }

  /**
   * Collects the violation property paths of a (unsaved) node.
   *
   * @return string[]
   *   The property paths of all validation violations.
   */
  protected function violationPaths(NodeInterface $node): array {
    $paths = [];
    foreach ($node->validate() as $violation) {
      $paths[] = $violation->getPropertyPath();
    }
    return $paths;
  }

  /**
   * Returns the violation paths that start with the given field name.
   */
  protected function pathsForField(array $paths, string $field): array {
    return array_values(array_filter(
      $paths,
      static fn(string $path): bool => str_starts_with($path, $field)
    ));
  }

  /**
   * Tests that a coupon without sponsor, campaign or code is rejected.
   */
  public function testMissingValuesAreRejected(): void {
    $sponsor = $this->createSponsor('Acme Corp');
    $campaign = $this->createCampaign('Fall Blast');

    $no_sponsor = $this->buildCoupon('SAVE20', $sponsor, $campaign);
    $no_sponsor->set('field_coupon_sponsor', []);
    $paths = $this->violationPaths($no_sponsor);
    $this->assertNotEmpty($this->pathsForField($paths, 'field_coupon_sponsor'), 'A coupon without a sponsor is rejected.');

    $no_campaign = $this->buildCoupon('SAVE20', $sponsor, $campaign);
    $no_campaign->set('field_coupon_campaign', []);
    $paths = $this->violationPaths($no_campaign);
    $this->assertNotEmpty($this->pathsForField($paths, 'field_coupon_campaign'), 'A coupon without a campaign is rejected.');

    $no_code = $this->buildCoupon('SAVE20', $sponsor, $campaign);
    $no_code->set('field_coupon_code', '');
    $paths = $this->violationPaths($no_code);
    $this->assertNotEmpty($this->pathsForField($paths, 'field_coupon_code'), 'A coupon without a code is rejected.');
  }

  /**
   * Tests that references are restricted to the right bundles.
   */
  public function testReferencesAreBundleRestricted(): void {
    $sponsor = $this->createSponsor('Acme Corp');
    $campaign = $this->createCampaign('Fall Blast');

    // A coupon whose sponsor field points at a campaign must be rejected.
    $wrong = $this->buildCoupon('SAVE20', $sponsor, $campaign);
    $wrong->set('field_coupon_sponsor', ['target_id' => $campaign->id()]);
    $paths = $this->violationPaths($wrong);
    $this->assertNotEmpty($this->pathsForField($paths, 'field_coupon_sponsor'), 'The sponsor field only accepts sponsor nodes.');
  }

  /**
   * Tests the code uniqueness per (sponsor, campaign), with trimming.
   */
  public function testDuplicateCodePerSponsorAndCampaignIsRejected(): void {
    $sponsor = $this->createSponsor('Acme Corp');
    $campaign = $this->createCampaign('Fall Blast');
    $existing = $this->buildCoupon('SAVE20', $sponsor, $campaign);
    $existing->save();

    // Exact duplicate.
    $duplicate = $this->buildCoupon('SAVE20', $sponsor, $campaign);
    $paths = $this->violationPaths($duplicate);
    $this->assertNotEmpty($this->pathsForField($paths, 'field_coupon_code'), 'The same code for the same sponsor and campaign is rejected.');

    // Duplicate that only differs by surrounding whitespace.
    $padded = $this->buildCoupon('  SAVE20  ', $sponsor, $campaign);
    $paths = $this->violationPaths($padded);
    $this->assertNotEmpty($this->pathsForField($paths, 'field_coupon_code'), 'Codes are trimmed before the uniqueness comparison.');
  }

  /**
   * Tests that the same code is allowed for another sponsor or campaign.
   */
  public function testSameCodeIsAllowedForDifferentSponsorOrCampaign(): void {
    $sponsor_a = $this->createSponsor('Acme Corp');
    $sponsor_b = $this->createSponsor('Beta LLC');
    $campaign_a = $this->createCampaign('Fall Blast');
    $campaign_b = $this->createCampaign('Winter Sale');
    $existing = $this->buildCoupon('SAVE20', $sponsor_a, $campaign_a);
    $existing->save();

    foreach ([
      'another sponsor' => $this->buildCoupon('SAVE20', $sponsor_b, $campaign_a),
      'another campaign' => $this->buildCoupon('SAVE20', $sponsor_a, $campaign_b),
    ] as $label => $coupon) {
      $paths = $this->violationPaths($coupon);
      $this->assertSame(
        [],
        $this->pathsForField($paths, 'field_coupon_code'),
        "The same code is allowed for $label."
      );
    }
  }

  /**
   * Tests the redeemed/redeemed-date constraint (issue requirement).
   */
  public function testRedeemedDateRule(): void {
    $sponsor = $this->createSponsor('Acme Corp');
    $campaign = $this->createCampaign('Fall Blast');

    $checks = [
      'redeemed without a date is rejected' => [
        ['field_coupon_redeemed' => 1],
        TRUE,
      ],
      'not redeemed with a date is rejected' => [
        [
          'field_coupon_redeemed' => 0,
          'field_coupon_redeemed_date' => '2026-10-16T09:30:00',
        ],
        TRUE,
      ],
      'redeemed with a date is accepted' => [
        [
          'field_coupon_redeemed' => 1,
          'field_coupon_redeemed_date' => '2026-10-16T09:30:00',
        ],
        FALSE,
      ],
      'not redeemed without a date is accepted' => [
        ['field_coupon_redeemed' => 0],
        FALSE,
      ],
    ];
    foreach ($checks as $label => [$overrides, $expects_violation]) {
      $coupon = $this->buildCoupon('SAVE20', $sponsor, $campaign, $overrides);
      $paths = $this->violationPaths($coupon);
      $found = $this->pathsForField($paths, 'field_coupon_redeemed_date');
      if ($expects_violation) {
        $this->assertNotEmpty($found, "A coupon $label.");
      }
      else {
        $this->assertSame([], $found, "A coupon $label.");
      }
    }
  }

}
