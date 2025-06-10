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
    if ($route_name === 'view.career_trek_node.page_1') {
      $arg0 = $route_match->getRawParameter('arg_0');
      $arg1 = $route_match->getRawParameter('arg_1');

      // Load the view and get the result for the current display.
      $view = \Drupal\views\Views::getView('career_trek_node');
      if ($view && isset($arg0)) {
        $view->setDisplay('page_1');
        $view->setArguments([$arg0, $arg1]);
        $view->execute();

        // Try to get the title field value from the result.
        $title = '';
        if (!empty($view->result)) {

          // Try to get the title field from the first result row.
          $row = $view->result[0];
          // Try to get the field value, fallback to arg0 if not found.
          if (isset($row->{'ssot_title|ssot_title'}) && is_array($row->{'ssot_title|ssot_title'}) && !empty($row->{'ssot_title|ssot_title'})) {
            // The value is usually in the first element of the array.
            $title = $row->{'ssot_title|ssot_title'}[0];
          }
        }
        if($title) {
          $route = \Drupal::routeMatch()->getCurrentRouteMatch()->getRouteObject();
          $route->setDefault('_title_callback', function() use ($title) {
            return $title;
          });
        }
      }
    }
  }
}
