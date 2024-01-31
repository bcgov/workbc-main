<?php

namespace Drupal\workbc_custom\EventSubscriber;

use Drupal\Core\Render\AttachmentsInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Adds the user's timezone to the drupalSettings JS object on all pages.
 */
class ResponseEventsSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      // Respond to KernelEvents::RESPONSE.
      KernelEvents::RESPONSE => ['onResponse', 1000],
    ];
  }

  /**
   * Event handler for KernelEvents::RESPONSE.
   */
  public function onResponse(\Symfony\Component\HttpKernel\Event\ResponseEvent $event) {
    // Only act upon the master request and not sub-requests.
    if ($event->isMasterRequest()) {
      $response = $event->getResponse();
      // Only act if the response is one that is able to have attachments.
      if ($response instanceof AttachmentsInterface) {
        // Get any existing attachments.
        $attachments = $response->getAttachments();
        // Set the mobile flag.
        $md = \Drupal::service('mobile_detect');
        $attachments['drupalSettings']['isMobile'] = $md->isMobile() && !$md->isTablet();
        // Set the updated array back on the object.
        $response->setAttachments($attachments);
      }
    }
  }

}
