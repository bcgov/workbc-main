<?php

namespace Drupal\Tests\workbc_ssot\Unit;

use Drupal\workbc_ssot\Form\SsotUploadLmmuForm;

/**
 * Test double of SsotUploadLmmuForm with the engine exposed.
 */
class TestLmmuForm extends SsotUploadLmmuForm {
  use EngineAccess;
}
