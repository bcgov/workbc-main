<?php

namespace Drupal\workbc_career_trek\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Controller\ControllerBase;
use Drupal\search_api\Entity\Index;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns autocomplete suggestions from Search API.
 */
class AutocompleteController extends ControllerBase {

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new AutocompleteController object.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   */
  public function __construct(LoggerInterface $logger) {
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory')->get('workbc_career_trek')
    );
  }

  /**
   * Returns JSON autocomplete suggestions.
   */
  public function getSuggestions(Request $request) {
    $search_term = $request->query->get('q');
    $results = [];

    if (mb_strlen($search_term) < 2) {
      return new JsonResponse($results);
    }

    // Load the Search API index.
    $index = Index::load('career_profile_index_sub');
    if (!$index) {
      return new JsonResponse($results);
    }

    $query = $index->query();
    $group = $query->createConditionGroup('OR');
    $group->addCondition('ssot_title', $search_term, 'CONTAINS');
    $group->addCondition('career_noc', $search_term, 'CONTAINS');
    $query->addConditionGroup($group);
    $query->range(0, 10);

    try {
      $result = $query->execute();

      foreach ($result as $item) {
        $title = $item->getField('ssot_title')->getValues();
        $noc = $item->getField('career_noc')->getValues();
        $episode_num = $item->getField('episode_num')->getValues();
        if(!empty($title) && !empty($noc)  && !empty($episode_num)) {
            $title = reset($title)->getText();
            $noc = reset($noc)->getText();
            $episode_num = reset($episode_num);

            $results[] = [
                'value' => $title . " ( NOC: ". $noc . " and Episode: " . $episode_num . " )",
                'url' => '/plan-career/career-trek-videos/' . $noc . "/". $episode_num,
            ]; 
        }
      }
    }
    catch (\Throwable $e) {
      // Use PSR-3 logger for Drupal 10.
      $this->logger->error('Autocomplete error: @message', ['@message' => $e->getMessage()]);
    }

    return new JsonResponse($results);
  }

}
