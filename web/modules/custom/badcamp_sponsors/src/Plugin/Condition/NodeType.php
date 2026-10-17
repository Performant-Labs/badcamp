<?php

declare(strict_types=1);

namespace Drupal\badcamp_sponsors\Plugin\Condition;

use Drupal\Core\Condition\Attribute\Condition;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a condition that restricts to selected node types.
 *
 * Core's bundle condition for nodes is the derived 'entity_bundle:node'
 * plugin; this module ships Pathauto patterns whose selection criteria are
 * written against the plain 'node_type' plugin id, so the plugin is provided
 * here, mirroring the entity bundle condition's behaviour.
 */
#[Condition(
  id: 'node_type',
  label: new TranslatableMarkup('Content type'),
  context_definitions: [
    'node' => new EntityContextDefinition(
      data_type: 'entity:node',
      label: new TranslatableMarkup('Node'),
    ),
  ]
)]
class NodeType extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected EntityTypeBundleInfoInterface $entityTypeBundleInfo;

  /**
   * Creates a new NodeType condition instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   */
  public function __construct(array $configuration, string $plugin_id, array $plugin_definition, EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.bundle.info'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['bundles' => []] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['bundles'] = [
      '#title' => $this->pluginDefinition['label'],
      '#type' => 'checkboxes',
      '#options' => $this->entityTypeBundleInfo->getBundleLabels('node'),
      '#default_value' => $this->configuration['bundles'],
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['bundles'] = array_filter($form_state->getValue('bundles'));
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    // Returns TRUE if no bundles are selected and negate option is disabled.
    if (empty($this->configuration['bundles']) && !$this->isNegated()) {
      return TRUE;
    }
    $node = $this->getContextValue('node');
    return $node !== NULL && !empty($this->configuration['bundles'][$node->bundle()]);
  }

  /**
   * {@inheritdoc}
   */
  public function summary(): string {
    if (count($this->configuration['bundles']) > 1) {
      $bundles = $this->configuration['bundles'];
      $last = array_pop($bundles);
      $bundles = implode(', ', $bundles);
      return $this->isNegated()
        ? $this->t('The content type is not @bundles or @last', ['@bundles' => $bundles, '@last' => $last])
        : $this->t('The content type is @bundles or @last', ['@bundles' => $bundles, '@last' => $last]);
    }
    $bundle = reset($this->configuration['bundles']);
    return $this->isNegated()
      ? $this->t('The content type is not @bundle', ['@bundle' => $bundle])
      : $this->t('The content type is @bundle', ['@bundle' => $bundle]);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $dependencies = parent::calculateDependencies();
    foreach (array_keys($this->configuration['bundles']) as $bundle) {
      $dependencies['config'][] = "node.type.{$bundle}";
    }
    return $dependencies;
  }

}
