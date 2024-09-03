<?php

namespace Drupal\workbc_ssot\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class SSotFeatureRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection)
  {
    if (empty(\Drupal::config('workbc')->get('features.ssot_upload'))) {
      foreach (SSOT_ROUTES as $route_name) {
        if ($route = $collection->get($route_name)) {
          $route->setRequirement('_access', 'FALSE');
        }
      }
    }
  }
}
