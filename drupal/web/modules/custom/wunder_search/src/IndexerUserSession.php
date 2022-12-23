<?php

namespace Drupal\wunder_search;

use Drupal\Core\Session\AnonymousUserSession;

/**
 * User session used for re-indexing.
 */
class IndexerUserSession extends AnonymousUserSession {

  /**
   * {@inheritdoc}
   */
  public function hasPermission($permission) {
    // Bypass the defined permission.
    // Use array for now in case we need to add more in the future.
    $bypass = ['bypass node access'];

    if (in_array($permission, $bypass)) {
      return TRUE;
    }

    return parent::hasPermission($permission);
  }

}
