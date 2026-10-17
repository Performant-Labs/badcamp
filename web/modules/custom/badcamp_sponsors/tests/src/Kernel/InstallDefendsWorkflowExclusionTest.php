<?php

declare(strict_types=1);

namespace Drupal\Tests\badcamp_sponsors\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\workflows\Entity\Workflow;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the install-time defense against workflow contamination.
 *
 * Walkthrough round 2 regression. On the live site the starter's
 * auto_enable_moderation ECA template adds every newly created content type
 * to the basic_editorial workflow, so by the time the module's hook_install()
 * runs the site's workflows already govern sponsor, campaign and coupon, and
 * every programmatic or form save is born draft (status forced to 0, canonical
 * routes denied). The ECA cannot run in the test environment, so this test
 * ships badcamp_sponsors_eca_sim, a helper module whose hook_node_type_insert()
 * reacts to node type creation the same way. With the helper enabled, the
 * contamination happens INSIDE the module's own config import - before
 * hook_install() - which is exactly the live sequence, and installing the
 * module must defend against it: remove the three bundles from every workflow
 * that covers node bundles, while every other governed bundle stays.
 *
 * This is the install-time defense; WorkflowExclusionTest covers the other
 * angle (a site without the ECA: installing the module contaminates no
 * workflow).
 *
 * @see \Drupal\Tests\badcamp_sponsors\Kernel\WorkflowExclusionTest
 */
#[Group('badcamp_sponsors')]
class InstallDefendsWorkflowExclusionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'workflows',
    'content_moderation',
    'badcamp_sponsors_eca_sim',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    // System config provides the date formats the token system needs when
    // pathauto runs on node save.
    $this->installConfig(['user', 'system']);
    $this->installEntitySchema('node');
    $this->installSchema('node', 'node_access');
    // Saving a node of a moderated bundle writes a content_moderation_state
    // entity, so its schema must exist before the (pre-fix) moderated saves
    // this test reproduces.
    $this->installEntitySchema('content_moderation_state');

    // Two bundles the module does not own, so the assertions can prove the
    // defense leaves other governed bundles alone.
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    // The site's basic_editorial workflow, governing page only before the
    // module is installed.
    $editorial = Workflow::create([
      'id' => 'basic_editorial',
      'label' => 'Basic editorial',
      'type' => 'content_moderation',
    ]);
    $editorial->getTypePlugin()->addEntityTypeAndBundle('node', 'page');
    $editorial->save();

    // A second workflow governing node bundles: the defense must clean every
    // covering workflow, not just the one named basic_editorial.
    $review = Workflow::create([
      'id' => 'coupon_review',
      'label' => 'Coupon review',
      'type' => 'content_moderation',
    ]);
    $review->getTypePlugin()->addEntityTypeAndBundle('node', 'article');
    $review->save();
  }

  /**
   * Asserts the ECA simulation behaves like the live template.
   *
   * Creates a throwaway node type, asserts both workflows pick it up, then
   * deletes the type again (core removes deleted bundles from workflows via
   * hook_entity_bundle_delete()). This guards the simulation: without it, a
   * silently broken helper module would leave the install-time assertions
   * below vacuously green after the fix.
   */
  protected function assertEcaSimulationFires(): void {
    NodeType::create(['type' => 'sim_probe', 'name' => 'Sim probe'])->save();
    foreach (['basic_editorial', 'coupon_review'] as $workflow_id) {
      $this->assertTrue(
        Workflow::load($workflow_id)->getTypePlugin()
          ->appliesToEntityTypeAndBundle('node', 'sim_probe'),
        "Sanity: the ECA simulation adds a new type to $workflow_id."
      );
    }
    NodeType::load('sim_probe')->delete();
  }

  /**
   * Tests that install removes the three bundles from every covering workflow.
   *
   * The defense runs in hook_install(), which executes after the module's
   * configuration import has created the node types and the ECA simulation
   * has contaminated the workflows, so it is the first place that can correct
   * that state.
   */
  public function testInstallRemovesModuleBundlesFromEveryNodeWorkflow(): void {
    $this->assertEcaSimulationFires();

    $this->container->get('module_installer')->install(['badcamp_sponsors']);

    // The basic_editorial workflow keeps governing page - and nothing else.
    $editorial = Workflow::load('basic_editorial');
    $this->assertNotNull($editorial, 'The pre-existing workflow survives the install.');
    $bundles = $editorial->getTypePlugin()->getBundlesForEntityType('node');
    sort($bundles);
    $this->assertSame(
      ['page'],
      $bundles,
      'basic_editorial still governs page and only page after install.'
    );

    // The second workflow is cleaned as well, and keeps its own other bundle.
    $review = Workflow::load('coupon_review');
    $this->assertNotNull($review, 'The second pre-existing workflow survives the install.');
    $bundles = $review->getTypePlugin()->getBundlesForEntityType('node');
    sort($bundles);
    $this->assertSame(
      ['article'],
      $bundles,
      'Every covering workflow is cleaned; article stays governed.'
    );

    // No workflow anywhere governs the module's bundles after install.
    foreach (Workflow::loadMultiple() as $workflow) {
      $plugin = $workflow->getTypePlugin();
      foreach (['sponsor', 'campaign', 'coupon'] as $bundle) {
        $this->assertFalse(
          $plugin->appliesToEntityTypeAndBundle('node', $bundle),
          "The {$workflow->id()} workflow does not govern the $bundle bundle."
        );
      }
    }
  }

  /**
   * Tests that nodes saved as published after install stay published.
   *
   * This is the operator-visible symptom the walkthrough found: a moderated
   * bundle forces every save to be born draft, so canonical routes deny
   * access to anonymous visitors. With the bundles excluded at install, a
   * node saved with status 1 is published.
   */
  public function testNodesSavedAsPublishedStayPublished(): void {
    $this->assertEcaSimulationFires();

    $this->container->get('module_installer')->install(['badcamp_sponsors']);

    /** @var \Drupal\node\NodeStorageInterface $storage */
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    /** @var \Drupal\content_moderation\ModerationInformationInterface $moderation_info */
    $moderation_info = $this->container->get('content_moderation.moderation_information');

    $sponsor = Node::create([
      'type' => 'sponsor',
      'title' => 'Acme Corp',
      'status' => 1,
    ]);
    $sponsor->save();

    $campaign = Node::create([
      'type' => 'campaign',
      'title' => 'Fall Blast',
      'status' => 1,
    ]);
    $campaign->save();

    $coupon = Node::create([
      'type' => 'coupon',
      'title' => 'Placeholder title',
      'status' => 1,
      'field_coupon_code' => 'SAVE20',
      'field_coupon_sponsor' => ['target_id' => $sponsor->id()],
      'field_coupon_campaign' => ['target_id' => $campaign->id()],
    ]);
    $coupon->save();

    $storage->resetCache();
    foreach (['sponsor' => $sponsor->id(), 'campaign' => $campaign->id(), 'coupon' => $coupon->id()] as $bundle => $nid) {
      $node = $storage->load($nid);
      $this->assertNotNull($node, "Sanity: the saved $bundle node loads.");
      $this->assertTrue(
        $node->isPublished(),
        "A $bundle saved with status 1 stays published."
      );
      $this->assertSame(
        1,
        (int) $node->get('status')->value,
        "A $bundle saved with status 1 keeps status value 1."
      );
      $this->assertFalse(
        $moderation_info->isModeratedEntity($node),
        "The saved $bundle node is not a moderated entity."
      );
    }
  }

}
