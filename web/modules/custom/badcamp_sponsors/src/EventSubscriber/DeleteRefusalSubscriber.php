<?php

declare(strict_types=1);

namespace Drupal\badcamp_sponsors\EventSubscriber;

use Drupal\badcamp_sponsors\DeleteRefusalReason;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Http\Exception\CacheableAccessDeniedHttpException;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Shows the coupon-count reason on a refused sponsor/campaign delete.
 *
 * A delete refusal by badcamp_sponsors_node_access() reaches the kernel as a
 * CacheableAccessDeniedHttpException whose message is the module's
 * coupon-count reason and whose cacheability carries the coupon list cache
 * tag. This subscriber answers that one exception with a 403 page whose body
 * shows the reason, preempting core's generic 403 page
 * (DefaultExceptionHtmlSubscriber, priority -128) at the stock priority 0 -
 * below ExceptionLoggingSubscriber (priority 50), so core's access-denied
 * watchdog log is untouched. Every other 403 - other routes, other reasons,
 * plain access-denied exceptions - is left to core's generic page.
 */
final class DeleteRefusalSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The route whose access denial can carry the coupon-count reason.
   */
  private const DELETE_ROUTE = 'entity.node.delete_form';

  /**
   * The renderer.
   */
  protected RendererInterface $renderer;

  /**
   * Constructs a new DeleteRefusalSubscriber.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(RendererInterface $renderer) {
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      // Priority 0: the stock default for module listeners, above
      // DefaultExceptionHtmlSubscriber (-128) and below
      // ExceptionLoggingSubscriber (50).
      KernelEvents::EXCEPTION => [['onException', 0]],
    ];
  }

  /**
   * Replaces the generic 403 of a coupon-refused delete with the reason.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The exception event.
   */
  public function onException(ExceptionEvent $event): void {
    $exception = $event->getThrowable();
    if (!$exception instanceof CacheableAccessDeniedHttpException) {
      return;
    }

    // The route name is on the request before the access check throws
    // (AccessAwareRouter::matchRequest() adds the route attributes first).
    $route_name = (string) $event->getRequest()->attributes->get(RouteObjectInterface::ROUTE_NAME, '');
    if ($route_name !== self::DELETE_ROUTE) {
      return;
    }

    // Scope by provenance, not mere non-emptiness: only reasons this module
    // built (DeleteRefusalReason::build()) qualify, so other modules'
    // access-denied reasons on the same route stay generic. The count is
    // never re-derived - it rides in the matched message.
    $reason = (string) $exception->getMessage();
    if (!DeleteRefusalReason::matches($reason)) {
      return;
    }

    // The same page shape core uses for its bare pages
    // (BareHtmlPageRenderer::renderBarePage()): a #type => 'page' element
    // whose content region holds the reason as #plain_text, the
    // escaped-verbatim contract, so the message can never inject markup.
    // Title parity with core's own 403 page.
    $page = [
      '#type' => 'page',
      '#title' => $this->t('Access denied'),
      'content' => ['#plain_text' => $reason],
    ];
    $content = (string) $this->renderer->renderRoot($page);

    // Response construction per FinalExceptionSubscriber::on4xx(): a
    // CacheableResponse whose cacheability comes from the exception alone
    // (it carries the access result's node_list:coupon tag), plus any
    // headers set on the exception. Setting the response stops propagation,
    // so core's generic 403 subrequest never runs.
    $response = new CacheableResponse($content, $exception->getStatusCode(), ['Content-Type' => 'text/html; charset=UTF-8']);
    $response->addCacheableDependency($exception);
    $response->headers->add($exception->getHeaders());
    $event->setResponse($response);
  }

}
