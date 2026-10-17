<?php

declare(strict_types=1);

namespace Drupal\Tests\badcamp_sponsors\Kernel;

use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests the HTTP response of a refused sponsor/campaign delete (issue #4).
 *
 * The access-level refusal itself is covered by DeleteBlockingTest; these
 * tests pin the RESPONSE contract of GET /node/{nid}/delete: when the
 * refusal reason is the module's coupon-count message, the 403 body must
 * carry that message and its cacheability (node_list:coupon); every other
 * delete outcome (form loads, permission-less users, other bundles) must
 * be unaffected.
 */
#[Group('badcamp_sponsors')]
class DeleteRefusalMessageTest extends KernelTestBase {

  use UserCreationTrait {
    createUser as drupalCreateUser;
  }

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
    // System config provides the date formats the token system needs when
    // pathauto runs on node save.
    $this->installConfig(['user', 'system']);
    // Trash must be told about node before the node entity schema exists,
    // otherwise the 'deleted' base field is missing its column.
    $this->config('trash.settings')
      ->set('enabled_entity_types', ['node' => []])
      ->save();
    $this->container->get('module_installer')->install(['badcamp_sponsors']);
    // User 1 bypasses node access; create it first so every account used
    // in the assertions is a regular user.
    $this->drupalCreateUser();

    // A refused or served delete page is rendered through the theme
    // system (the generic 403 arrives as a /system/403 subrequest), so
    // the site needs one theme.
    $this->container->get('theme_installer')->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();

    // The node delete-form routes come from entity route providers; make
    // sure the router table knows them before requests are matched.
    $this->container->get('router.builder')->rebuild();
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
   * Creates two sponsors, two campaigns and two coupons (one later trashed).
   *
   * @return array
   *   [sponsor_a, sponsor_b, campaign_a, campaign_b, live, trashed]
   */
  protected function createCouponFixtures(): array {
    $sponsor_a = $this->createSponsor('Acme Corp');
    $sponsor_b = $this->createSponsor('Beta LLC');
    $campaign_a = $this->createCampaign('Fall Blast');
    $campaign_b = $this->createCampaign('Winter Sale');

    // A live coupon referencing sponsor A / campaign A.
    $live = $this->createCoupon('SAVE20', $sponsor_a, $campaign_a);
    // A coupon referencing sponsor A / campaign B, then put in trash: with
    // trash enabled for node and the trash context 'active' (the default),
    // delete() soft-deletes.
    $trashed = $this->createCoupon('SAVE21', $sponsor_a, $campaign_b);
    $trashed->delete();

    return [$sponsor_a, $sponsor_b, $campaign_a, $campaign_b, $live, $trashed];
  }

  /**
   * Requests the node delete confirmation page as the given user.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node whose delete form is requested.
   * @param \Drupal\user\UserInterface $account
   *   The account the request runs as.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response of the full request pipeline.
   */
  protected function requestDeleteForm(NodeInterface $node, UserInterface $account): Response {
    $this->container->get('current_user')->setAccount($account);
    return $this->container->get('http_kernel')->handle(
      Request::create('/node/' . $node->id() . '/delete')
    );
  }

  /**
   * Tests the sponsor delete 403 body, cacheability and untouched cases.
   */
  public function testRefusedSponsorDeleteRendersCouponCountReason(): void {
    // Sponsor A is referenced by one live and one trashed coupon; sponsor
    // B is unreferenced.
    [$sponsor_a, $sponsor_b] = $this->createCouponFixtures();
    $manager = $this->drupalCreateUser([
      'access content',
      'delete any sponsor content',
    ]);

    // AC 1 (sponsor): the refused delete answers 403 and the body carries
    // the existing reason, with bundle name and live+trashed count
    // interpolated.
    $response = $this->requestDeleteForm($sponsor_a, $manager);
    $this->assertSame(403, $response->getStatusCode(), 'A referenced sponsor delete is refused.');
    $this->assertStringContainsString(
      'This sponsor is referenced by 2 coupon(s), including coupons in trash.',
      (string) $response->getContent(),
      'The 403 body shows the coupon-count reason.'
    );

    // AC 5: the refusal's cacheability (node_list:coupon from the access
    // result) rides on the response.
    $this->assertInstanceOf(CacheableResponseInterface::class, $response);
    $this->assertContains(
      'node_list:coupon',
      $response->getCacheableMetadata()->getCacheTags(),
      'The 403 response varies with the coupon list.'
    );

    // AC 3: an unreferenced sponsor keeps a working delete form.
    $response = $this->requestDeleteForm($sponsor_b, $manager);
    $this->assertSame(
      200,
      $response->getStatusCode(),
      'The delete form loads for a permitted user on an unreferenced sponsor.'
    );
    $form_body = (string) $response->getContent();
    $this->assertStringContainsString(
      'node-sponsor-delete-form',
      $form_body,
      'The 200 response is the real sponsor delete confirm form.'
    );
    $this->assertStringContainsString('value="Delete"', $form_body);

    // AC 4(a): a user without the delete permission gets core's generic
    // 403, never the coupon-count reason. The node is unreferenced, so
    // only the missing permission refuses the request.
    $plain = $this->drupalCreateUser(['access content']);
    $response = $this->requestDeleteForm($sponsor_b, $plain);
    $this->assertSame(403, $response->getStatusCode());
    $this->assertStringNotContainsString(
      'referenced by',
      (string) $response->getContent(),
      'A permission-less delete keeps the generic 403 body.'
    );

    // AC 4 (provenance scope): a NON-EMPTY reason that is not this
    // module's must stay generic too. A user without 'access content'
    // trips node core's "The 'access content' permission is required."
    // refusal on the same delete route — the one in-environment foreign
    // reason that reaches the subscriber — so scoping by provenance and
    // scoping by mere non-emptiness are distinguishable here.
    $no_access = $this->drupalCreateUser([]);
    $response = $this->requestDeleteForm($sponsor_b, $no_access);
    $this->assertSame(403, $response->getStatusCode());
    $this->assertStringContainsString(
      'You are not authorized to access this page.',
      (string) $response->getContent(),
      "Core's own access reason on the delete route keeps the generic 403 page."
    );
    $this->assertStringNotContainsString(
      "The 'access content' permission is required.",
      (string) $response->getContent(),
      'A foreign reason is not rendered as the 403 body.'
    );
  }

  /**
   * Tests the campaign delete 403 body and that other bundles stay generic.
   */
  public function testRefusedCampaignDeleteRendersCouponCountReason(): void {
    // Campaign A gets a second, trashed coupon on top of the live one from
    // the fixtures: 2 referencing coupons, one live and one trashed, so
    // the interpolated count and bundle name are both pinned.
    [, $sponsor_b, $campaign_a, , $live] = $this->createCouponFixtures();
    $extra = $this->createCoupon('SAVE22', $sponsor_b, $campaign_a);
    $extra->delete();

    $manager = $this->drupalCreateUser([
      'access content',
      'delete any campaign content',
    ]);

    // AC 1 (campaign): the refusal names the campaign bundle and its own
    // coupon count.
    $response = $this->requestDeleteForm($campaign_a, $manager);
    $this->assertSame(403, $response->getStatusCode(), 'A referenced campaign delete is refused.');
    $this->assertStringContainsString(
      'This campaign is referenced by 2 coupon(s), including coupons in trash.',
      (string) $response->getContent(),
      'The 403 body shows the campaign coupon-count reason.'
    );

    // AC 4(b): a delete refusal on another bundle (coupon) keeps core's
    // generic 403 body. The standard profile's 'page' type does not exist
    // in the kernel test environment, so the coupon bundle stands in.
    $plain = $this->drupalCreateUser(['access content']);
    $response = $this->requestDeleteForm($live, $plain);
    $this->assertSame(403, $response->getStatusCode());
    $this->assertStringNotContainsString(
      'referenced by',
      (string) $response->getContent(),
      'A coupon delete refusal does not show the sponsor/campaign reason.'
    );
    $this->assertStringContainsString(
      'You are not authorized to access this page.',
      (string) $response->getContent(),
      'Other bundles keep core\'s generic 403 message.'
    );

    // AC 3 (campaign): an unreferenced campaign keeps a working delete
    // form — the campaign twin of test 1's sponsor guard. campaign_b from
    // the fixtures is referenced by the trashed coupon, so build a fresh
    // unreferenced one.
    $unreferenced = $this->createCampaign('Spring Fling');
    $response = $this->requestDeleteForm($unreferenced, $manager);
    $this->assertSame(
      200,
      $response->getStatusCode(),
      'The delete form loads for a permitted user on an unreferenced campaign.'
    );
    $campaign_body = (string) $response->getContent();
    $this->assertStringContainsString(
      'node-campaign-delete-form',
      $campaign_body,
      'The 200 response is the real campaign delete confirm form.'
    );
    $this->assertStringContainsString('value="Delete"', $campaign_body);
  }

}
