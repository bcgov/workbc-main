<?php

namespace Drupal\career_chat\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Career Chat' block.
 *
 * @Block(
 * id = "career_chat_block",
 * admin_label = @Translation("Career AI Chat Advisor"),
 * category = @Translation("AI Tools")
 * )
 */
class ChatBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      // The HTML div React will mount into
      '#markup' => '<div id="career-chat-root"></div>',
      '#attached' => [
        'library' => [
          'career_chat/chat-app',
        ],
        // Passing the API URL to React safely via drupalSettings
        'drupalSettings' => [
          'careerChat' => [
            'apiUrl' => 'http://localhost:8000/ask',
          ],
        ],
      ],
    ];
  }
}