<?php

declare(strict_types=1);

namespace Drupal\Tests\badcamp_sponsors\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\workflows\Entity\Workflow;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the three types are never added to an editorial workflow.
 */
#[Group('badcamp_sponsors')]
class WorkflowExclusionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'workflows',
    'content_moderation',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['user']);
    $this->installEntitySchema('node');
    $this->installSchema('node', 'node_access');

    // Reproduce the site's basic_editorial workflow governing page only.
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    $workflow = Workflow::create([
      'id' => 'basic_editorial',
      'label' => 'Basic editorial',
      'type' => 'content_moderation',
    ]);
    $workflow->getTypePlugin()->addEntityTypeAndBundle('node', 'page');
    $workflow->save();

    $this->container->get('module_installer')->install(['badcamp_sponsors']);
  }

  /**
   * Tests that installing the module leaves the workflow untouched.
   */
  public function testTypesAreNotAddedToEditorialWorkflow(): void {
    $workflow = Workflow::load('basic_editorial');
    $this->assertNotNull($workflow, 'Sanity: the basic_editorial workflow exists.');
    $plugin = $workflow->getTypePlugin();

    // The workflow still governs page only.
    $this->assertSame(
      ['page'],
      $plugin->getBundlesForEntityType('node'),
      'The workflow still governs page only.'
    );
    foreach (['sponsor', 'campaign', 'coupon'] as $bundle) {
      $this->assertFalse(
        $plugin->appliesToEntityTypeAndBundle('node', $bundle),
        "The $bundle type is not moderated."
      );
    }

    // The module ships no workflow config of its own.
    $this->assertSame(
      ['workflows.workflow.basic_editorial'],
      $this->container->get('config.storage')->listAll('workflows.workflow.'),
      'The module ships no additional workflows.'
    );
  }

}
