<?php

namespace Drupal\workbc_career_trek\Plugin\search_api\parse_mode;

use Drupal\Component\Utility\Unicode;
use Drupal\search_api\ParseMode\ParseModePluginBase;
use Drupal\search_api\Query\QueryInterface;

/**
 * Represents a parse mode that parses the input into multiple words.
 *
 * @SearchApiParseMode(
 *   id = "career_trek_terms",
 *   label = @Translation("Career Trek Multiple words"),
 *   description = @Translation("The query is interpreted as multiple keywords separated by spaces. Keywords containing spaces may be ""quoted"". Quoted keywords must still be separated by spaces. Keywords can be negated by prepending a minus sign (-) to them. Use of 'or' and '&' will be treated with an AND clause. Use of commas will be treated with an OR clause."),
 * )
 */
class CareerTrekTerms extends ParseModePluginBase {

  /**
   * {@inheritdoc}
   */
  public function parseInput($keys) {
    $ret = [
      '#conjunction' => $this->getConjunction(),
    ];

    if (!Unicode::validateUtf8($keys)) {
      return $ret;
    }
    // Split the keys into tokens. Any whitespace is considered as a delimiter
    // for tokens. This covers ASCII white spaces as well as multi-byte "spaces"
    // which for example are common in Japanese.
    $tokens = preg_split('/\s+/u', $keys) ?: [];
    $quoted = FALSE;
    $negated = FALSE;
    $phrase_contents = [];

    foreach ($tokens as $token) {
      // Ignore empty tokens. (Also helps keep the following code simpler.)
      if ($token === '') {
        continue;
      }

      // Check for negation.
      if ($token[0] === '-' && !$quoted) {
        $token = ltrim($token, '-');
        // If token is empty after trimming, ignore it.
        if ($token === '') {
          continue;
        }
        $negated = TRUE;
      }

      // Depending on whether we are currently in a quoted phrase, or maybe just
      // starting one, act accordingly.
      if ($quoted) {
        if (str_ends_with($token, '"')) {
          $token = substr($token, 0, -1);
          $phrase_contents[] = trim($token);
          $phrase_contents = array_filter($phrase_contents, 'strlen');
          $phrase_contents = implode(' ', $phrase_contents);
          if ($phrase_contents !== '') {
            $ret[] = $phrase_contents;
          }
          $quoted = FALSE;
        }
        else {
          $phrase_contents[] = trim($token);
          continue;
        }
      }
      elseif ($token[0] === '"') {
        $len = strlen($token);
        if ($len > 1 && $token[$len - 1] === '"') {
          $ret[] = substr($token, 1, -1);
        }
        else {
          $phrase_contents = [trim(substr($token, 1))];
          $quoted = TRUE;
          continue;
        }
      }
      else {
        // Handle 'or' and '&' as AND conjunctions.
        if ($token === '&' || $token == 'and') {
          $ret['#conjunction'] = 'AND';
          continue;
        }
        // Handle 'or' and commas as OR conjunctions.
        if (strtolower($token) === 'or' || $token === ',') {
          $ret['#conjunction'] = 'OR';
          continue;
        }
        // New condition: match numeric tokens strictly as "starts with full string"
        if (ctype_digit($token)) {
            $ret[] = [
              '#full_numeric_prefix' => TRUE,
              'value' => $token,
            ];
          }
        else {
            $ret[] = $token;
        }          
      }

      // If negation was set, change the last added keyword to be negated.
      if ($negated) {
        $i = count($ret) - 2;
        $ret[$i] = [
          '#negation' => TRUE,
          '#conjunction' => 'AND',
          $ret[$i],
        ];
        $negated = FALSE;
      }
    }

    // Take care of any quoted phrase missing its closing quotation mark.
    if ($quoted) {
      $phrase_contents = implode(' ', array_filter($phrase_contents, 'strlen'));
      if ($phrase_contents !== '') {
        $ret[] = $phrase_contents;
      }
    }

    return $ret;
  }

}