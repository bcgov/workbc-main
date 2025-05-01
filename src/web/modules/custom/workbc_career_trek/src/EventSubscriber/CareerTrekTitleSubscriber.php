<?php

namespace Drupal\workbc_career_trek\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Sets dynamic page titles for Career Trek view pages.
 */
class CareerTrekTitleSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => ['setDynamicTitle', 30],
    ];
  }

  public function setDynamicTitle(RequestEvent $event) {
    $request = $event->getRequest();

    // Only run on master requests.
    if (!$event->isMainRequest()) {
      return;
    }

    /** @var \Drupal\Core\Routing\RouteMatchInterface $route_match */
    $route_match = \Drupal::service('current_route_match');
    $route_name = $route_match->getRouteName();

    if ($route_name === 'view.career_trek_videos.page_1') {
      $arg = $route_match->getRawParameter('arg_0');
      if ($arg) {
        $title = ucwords(str_replace('-', ' ', $arg));
        $route = \Drupal::routeMatch()->getCurrentRouteMatch()->getRouteObject();

        $route->setDefault('_title_callback', function() use ($title) {
            return $title;
        });

        // Set the title in the request attributes so it overrides everything.
        // $request->attributes->set('_title_callback', $title);
      }
    }
  }
}
