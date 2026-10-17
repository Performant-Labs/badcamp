<?php

declare(strict_types=1);

namespace Drupal\badcamp_sponsors\Plugin\views\access;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsAccess;
use Drupal\views\Plugin\views\access\AccessPluginBase;
use Symfony\Component\Routing\Route;

/**
 * Access plugin requiring both Sponsors-section and coupon permissions.
 *
 * Views' Permission plugin checks a single permission string, so it cannot
 * express "both permissions" at the display level: the comma-separated
 * string is only AND-split by the route layer's permission access check.
 * This plugin ANDs the two permissions for the display-level render gate
 * and re-sets the same comma-separated requirement on the route, keeping
 * the route layer's behavior unchanged.
 *
 * access() must keep returning a bool, not an AccessResult: the display
 * gate consumes it by truthiness (ViewExecutable::access() and the view
 * element's pre-render), where a Forbidden object would be truthy and
 * flip every denial into an allow. The user.permissions cacheability an
 * AccessResult would carry is provided via CacheableDependencyInterface
 * instead, matching core's Permission plugin.
 *
 * @ingroup views_access_plugins
 */
#[ViewsAccess(
  id: 'badcamp_sponsors_section_and_coupon',
  title: new TranslatableMarkup('Sponsors section and coupon permissions'),
  help: new TranslatableMarkup('Access will be granted only to users holding both the "access sponsors section" and "view coupon content" permissions.'),
)]
final class SectionAndCouponAccess extends AccessPluginBase implements CacheableDependencyInterface {

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    return $account->hasPermission('access sponsors section')
      && $account->hasPermission('view coupon content');
  }

  /**
   * {@inheritdoc}
   */
  public function alterRouteDefinition(Route $route) {
    $route->setRequirement('_permission', 'access sponsors section,view coupon content');
  }

  /**
   * {@inheritdoc}
   */
  public function summaryTitle() {
    return $this->t('Access the Sponsors section + View coupon content');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['user.permissions'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return [];
  }

}
