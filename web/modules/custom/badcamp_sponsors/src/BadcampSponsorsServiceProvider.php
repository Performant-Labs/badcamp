<?php

declare(strict_types=1);

namespace Drupal\badcamp_sponsors;

use Drupal\badcamp_sponsors\Access\TrimmingPermissionAccessCheck;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Swaps in the module's whitespace-tolerant permission access check.
 *
 * The coupons page route requires '_permission: 'access sponsors section,
 * view coupon content''. Core's checker splits that requirement on commas
 * without trimming, which would make the second permission unmatchable; see
 * \Drupal\badcamp_sponsors\Access\TrimmingPermissionAccessCheck.
 */
class BadcampSponsorsServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    if ($container->hasDefinition('access_check.permission')) {
      $container->getDefinition('access_check.permission')
        ->setClass(TrimmingPermissionAccessCheck::class);
    }
  }

}
