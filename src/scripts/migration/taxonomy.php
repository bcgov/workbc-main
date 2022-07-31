<?php

/**
* Generate any taxonomy vocabulary from a CSV sheet.
*
* Usage: drush scr /scripts/migration/taxonomy -- -v vocabulary_name /full/path/to/sheet.csv
*
* Revert: drush entity:delete taxonomy_term --bundle=vocabulary_name
*/

$getopt = new \GetOpt\GetOpt([
    ['h', 'help', \GetOpt\GetOpt::NO_ARGUMENT, 'Show this help and quit'],
    ['v', 'vocabulary', \GetOpt\GetOpt::REQUIRED_ARGUMENT, 'Target vocabulary identifier'],
], [\GetOpt\GetOpt::SETTING_STRICT_OPERANDS => true]);
$getopt->addOperand(\GetOpt\Operand::create('file', \GetOpt\Operand::REQUIRED)->setValidation('is_file'));
try {
    $getopt->process($extra);
}
catch (Exception $e) {
    die($getopt->getHelpText() . PHP_EOL);
}
$file = $getopt->getOperand('file');
$vocabulary = $getopt->getOption('vocabulary');
print("Importing $file into $vocabulary\n");

// Derive taxonomy field names.
// Expecting at least columns TITLE/NAME and DESCRIPTION.
// All other columns will be saved as field_<column>.
$headers = [];
$handle = fopen($file, 'r');
while (($data = fgetcsv($handle)) !== FALSE) {
    if (empty($headers)) {
        // Build the headers list by associating the taxonony field name to its CSV column.
        foreach ($data as $i => $header) {
            if (strcasecmp($header, 'title') === 0 || strcasecmp($header, 'name') === 0) {
                $headers['name'] = $i;
            }
            else if (strcasecmp($header, 'description') === 0) {
                $headers['description'] = $i;
            }
            else {
                $headers['field_' . strtolower($header)] = $i;
            }
        }
        continue;
    }

    // Build the taxonomy fields from the incoming CSV row.
    $fields = [
        'vid' => $vocabulary,
    ];
    foreach ($headers as $field => $i) {
        $fields[$field] = $data[$i];
    }
    print("Creating {$fields['name']}\n");
    $term = Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->create($fields);
    $term->save();
}
fclose($handle);
