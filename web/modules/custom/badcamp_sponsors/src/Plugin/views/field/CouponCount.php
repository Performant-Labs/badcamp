<?php

declare(strict_types=1);

namespace Drupal\badcamp_sponsors\Plugin\views\field;

use Drupal\badcamp_sponsors\CouponCounter;
use Drupal\node\NodeInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shows how many coupons reference each row's sponsor or campaign.
 *
 * The count comes from the coupon counter service, so it includes coupons
 * sitting in trash - the same number the delete-block rule uses. The count is
 * computed per row and is not added to the SQL query, so the column is not
 * click-sortable.
 */
#[ViewsField("badcamp_sponsors_coupon_count")]
final class CouponCount extends FieldPluginBase {

  /**
   * The coupon counter.
   *
   * @var \Drupal\badcamp_sponsors\CouponCounter
   */
  protected CouponCounter $couponCounter;

  /**
   * Constructs a CouponCount field plugin.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \Drupal\badcamp_sponsors\CouponCounter $coupon_counter
   *   The coupon counter.
   */
  public function __construct(array $configuration, string $plugin_id, array $plugin_definition, CouponCounter $coupon_counter) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->couponCounter = $coupon_counter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('badcamp_sponsors.coupon_counter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['counter'] = ['default' => 'sponsor'];
    return $options;
  }

  /**
   * {@inheritdoc}
   *
   * Nothing is added to the query: the count is computed per rendered row.
   */
  public function query(): void {}

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $node = $this->getEntity($values);
    if (!$node instanceof NodeInterface) {
      return NULL;
    }
    $count = $this->options['counter'] === 'campaign'
      ? $this->couponCounter->countByCampaign($node)
      : $this->couponCounter->countBySponsor($node);
    return (string) $count;
  }

}
