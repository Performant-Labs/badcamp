<?php

declare(strict_types=1);

namespace Drupal\badcamp_sponsors\Access;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Access\PermissionAccessCheck;
use Symfony\Component\Routing\Route;

/**
 * Whitespace-tolerant version of core's permission access check.
 *
 * Core's PermissionAccessCheck splits comma-separated '_permission'
 * requirements with a bare explode(), so a requirement written as
 * 'permission one, permission two' never matches 'permission two' (the
 * leading space is kept). The Sponsors section's coupons page ships exactly
 * such a requirement, and comma-separated permission lists with spaces are
 * the documented way to write AND-conjunctions, so this subclass trims each
 * conjunct before delegating to core's logic. Single permissions and
 * comma-separated lists without spaces behave exactly as before.
 */
class TrimmingPermissionAccessCheck extends PermissionAccessCheck {

  /**
   * {@inheritdoc}
   */
  public function access(Route $route, AccountInterface $account): AccessResultInterface {
    $permission = $route->getRequirement('_permission');
    if ($permission !== NULL && str_contains($permission, ',')) {
      $trimmed = array_map(trim(...), explode(',', $permission));
      // Clone so the original route object (and its cached variants) keep
      // the requirement exactly as shipped.
      $normalized_route = clone $route;
      $normalized_route->setRequirement('_permission', implode(',', $trimmed));
      $route = $normalized_route;
    }
    return parent::access($route, $account);
  }

}
