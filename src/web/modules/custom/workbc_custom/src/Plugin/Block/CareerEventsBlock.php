<?php

namespace Drupal\workbc_custom\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\views\Views;

/**
 * Provides a WorkBC Related topics Block.
 *
 * @Block(
 *   id = "career_events_block",
 *   admin_label = @Translation("WorkBC career events block"),
 *   category = @Translation("WorkBC"),
 * )
 */
class CareerEventsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {


    $view1 = Views::getView('career_events');
    $view1->setDisplay('block_1');
    $list = $view1->buildRenderable();

    $view2 = Views::getView('career_events');
    $view2->setDisplay('block_2');
    $calendar = $view2->buildRenderable();

    $career_events = array(
      'list' => $list,
      'calendar' => $calendar,
    );

    $renderable = [
      '#theme' => 'career_events_block',
      '#career_events' => $career_events,
    ];

    return $renderable;
  }

}
