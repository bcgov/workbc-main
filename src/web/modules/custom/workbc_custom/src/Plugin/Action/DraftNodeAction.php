<?php

namespace Drupal\workbc_custom\Plugin\Action;

use Drupal\node\Entity\Node;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Access\AccessResult;

/**
 * An example action covering most of the possible options.
 *
 * If type is left empty, action will be selectable for all
 * entity types.
 *
 * @Action(
 *   id = "workbc_draft_node_action",
 *   label = @Translation("Workflow - Draft"),
 *   type = "node",
 *   confirm = TRUE,
 * )
 */
class DraftNodeAction extends ViewsBulkOperationsActionBase  {

  /**
   * {@inheritdoc}
   */
   public function execute(ContentEntityInterface $entity = NULL) {
     if (!$state = $entity->get('moderation_state')->getString()) {
       return $this->t(':title  - can\'t change state',
         [
           ':title' => $entity->getTitle(),
         ]
       );
     }

     switch ($state) {
       case 'published':
       case 'archived':
       case 'review':
         $entity->set('moderation_state', 'draft');
         $entity->save();
         break;
     }

     return $this->t(':title state changed to :state',
       [
         ':title' => $entity->getTitle(),
         ':state' => $entity->get('moderation_state')->getString(),
       ]
     );
   }

   /**
    * {@inheritdoc}
    */
   public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
     if ($object instanceof Node) {
       if (!\Drupal::currentUser()->hasPermission('use editorial transition create_new_draft')) {
         return AccessResult::forbidden();
       }
       return $object->access('update', $account, $return_as_object);
     }

     return FALSE;
   }
}
