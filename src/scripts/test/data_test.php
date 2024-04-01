#!/usr/bin/env php
<?php

$errors = noc2021ProcessValidation();

echo "\nNOC 2021 Migration Validation\n\n";

if (empty($errors)) {
  echo "No validation errors found.\n\n";
}
else {
  foreach ($errors as $error) {
    echo  $error . "\n";
  }
}