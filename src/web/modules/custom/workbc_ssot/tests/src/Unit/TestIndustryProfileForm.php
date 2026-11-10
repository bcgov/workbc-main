<?php

namespace Drupal\Tests\workbc_ssot\Unit;

use Drupal\workbc_ssot\Form\SsotUploadIndustryProfileForm;

/**
 * Test double of SsotUploadIndustryProfileForm with the engine exposed.
 */
class TestIndustryProfileForm extends SsotUploadIndustryProfileForm {
  use EngineAccess;
}
