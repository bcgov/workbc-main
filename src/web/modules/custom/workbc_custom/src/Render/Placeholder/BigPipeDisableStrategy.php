<?php

namespace Drupal\workbc_custom\Render\Placeholder;

use Drupal\big_pipe\Render\Placeholder\BigPipeStrategy;
use Drupal\Core\Render\Placeholder\PlaceholderStrategyInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Allows opting out from BigPipe by when specific WorkBC paths are requested.
 *
 * @see \Drupal\workbc_custom\Render\Placeholder\BigPipeStrategy
 */
class BigPipeDisableStrategy extends BigPipeStrategy {

  /**
   * The decorated BigPipe placeholder strategy.
   *
   * @var \Drupal\Core\Render\Placeholder\PlaceholderStrategyInterface
   */
  protected $bigPipeStrategy;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a new BigPipeDisableStrategy class.
   *
   * @param \Drupal\Core\Render\Placeholder\PlaceholderStrategyInterface $big_pipe_strategy
   *   The decorated BigPipe placeholder strategy.
   * @param \Drupal\Core\Session\SessionConfigurationInterface $session_configuration
   *   The session configuration.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  public function __construct(PlaceholderStrategyInterface $big_pipe_strategy, $session_configuration, RequestStack $request_stack, RouteMatchInterface $route_match) {
    $this->bigPipeStrategy= $big_pipe_strategy;
    parent::__construct($session_configuration, $request_stack, $route_match);

    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public function processPlaceholders(array $placeholders) {
    $current_uri = \Drupal::request()->getRequestUri();
    $paths = \Drupal::config('workbc')->get('paths');
    foreach ([$paths['career_exploration_search'], $paths['career_trek']] as $path) {
      if (str_starts_with($current_uri, $path)) {
        return [];
      }
    }

    return $this->bigPipeStrategy->processPlaceholders($placeholders);
  }

}
