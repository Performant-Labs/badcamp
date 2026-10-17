<?php

/**
 * @file
 * Provide views data for the BADCAMP Sponsors module.
 */

declare(strict_types=1);

/**
 * Implements hook_views_data().
 *
 * Both shipped list views reference the coupon-count column as
 * (node_field_data, badcamp_sponsors_coupon_count), and Views resolves a
 * config-referenced field handler through this data - without the entry the
 * handler falls back to the 'broken' plugin and every cell renders empty.
 * The count is computed per rendered row, so only a 'field' key is declared:
 * no sort, filter or argument handlers exist for it.
 */
function badcamp_sponsors_views_data(): array {
  $data['node_field_data']['badcamp_sponsors_coupon_count'] = [
    'title' => t('Coupons'),
    'help' => t("Number of coupons referencing the row's sponsor or campaign, including trashed ones; computed per row, so the column is not sortable."),
    'field' => [
      'id' => 'badcamp_sponsors_coupon_count',
    ],
  ];

  return $data;
}
