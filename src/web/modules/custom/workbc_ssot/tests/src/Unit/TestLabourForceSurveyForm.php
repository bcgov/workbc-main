<?php

namespace Drupal\Tests\workbc_ssot\Unit;

use Drupal\workbc_ssot\Form\SsotUploadLabourForceSurveyForm;

/**
 * Test double of SsotUploadLabourForceSurveyForm with the engine exposed.
 */
class TestLabourForceSurveyForm extends SsotUploadLabourForceSurveyForm {
  use EngineAccess;
}
