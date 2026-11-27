<?php

namespace Drupal\workbc_ssot\Routing;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\PermissionHandlerInterface;

/**
 * Checks access to the upload landing page.
 */
class SsotUploadAccess {

  public const ADMIN_PERMISSION = 'administer ssot settings';
  public const SHEET_PERMISSION_PREFIX = 'upload ssot data sheet ';

  public function __construct(private PermissionHandlerInterface $permissionHandler) {}

  /**
   * Allows administrators and users with any registered sheet permission.
   */
  public function access(AccountInterface $account): AccessResult {
    $permissions = [self::ADMIN_PERMISSION];
    foreach ($this->permissionHandler->getPermissions() as $name => $definition) {
      if ($definition['provider'] === 'workbc_ssot' && str_starts_with($name, self::SHEET_PERMISSION_PREFIX)) {
        $permissions[] = $name;
      }
    }

    return AccessResult::allowedIfHasPermissions($account, $permissions, 'OR');
  }

}
