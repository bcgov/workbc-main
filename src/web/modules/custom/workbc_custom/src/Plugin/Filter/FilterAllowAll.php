<?php

namespace Drupal\workbc_custom\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormStateInterface;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * Provides a filter to allow html.
 *
 * @Filter(
 *   id = "filter_allow_all",
 *   title = @Translation("Allow all HTML tags & attributes and correct faulty HTML"),
 *   description = @Translation("Allows all HTML tags & attributes that are not part of the standard CkEditor 5 list.."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_HTML_RESTRICTOR
 * )
 */
class FilterAllowAll extends FilterBase {

  /**
   * The processed HTML restrictions.
   *
   * @var array
   */
  protected $restrictions;

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
      if (empty($this->settings['allowed_html'])) {
          $this->settings['allowed_html'] = '<br> <p> <h2> <h3> <h4> <h5> <h6> <strong> <em> <sup> <blockquote> <a href> <ul> <ol start> <li> <table> <tr> <td rowspan colspan> <th rowspan colspan> <thead> <tbody> <tfoot> <caption> <drupal-media data-entity-type data-entity-uuid alt>';
      }

    $form['allowed_html'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowed HTML tags'),
      '#default_value' => $this->settings['allowed_html'],
      '#description' => $this->t('A list of HTML tags that can be used. These are required for text format validation. However, this filter allows all tags & attributes whether included in this list or not to be used in editor.</p>'),
      '#attached' => [
        'library' => [
          'filter/drupal.filter.filter_html.admin',
        ],
      ],
    ];
    // $form['filter_html_help'] = [
    //   '#type' => 'checkbox',
    //   '#title' => $this->t('Display basic HTML help in long filter tips'),
    //   '#default_value' => isset($this->settings['filter_html_help']) ? $this->settings['filter_html_help'] : null,
    // ];

    // $form['filter_html_nofollow'] = [
    //   '#type' => 'checkbox',
    //   '#title' => $this->t('Add rel="nofollow" to all links'),
    //   '#default_value' => isset($this->settings['filter_html_nofollow']) ? $this->settings['filter_html_nofollow'] : null,
    // ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    if (isset($configuration['settings']['allowed_html'])) {
      // The javascript in core/modules/filter/filter.filter_html.admin.js
      // removes new lines and double spaces so, for consistency when javascript
      // is disabled, remove them.
      $configuration['settings']['allowed_html'] = preg_replace('/\s+/', ' ', $configuration['settings']['allowed_html']);
    }
    parent::setConfiguration($configuration);
    // Force restrictions to be calculated again.
    $this->restrictions = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {

    $restrictions = $this->getHtmlRestrictions();
    unset($restrictions['allowed']['*']);

    return new FilterProcessResult($text);

  }


  /**
   * {@inheritdoc}
   */
  public function getHTMLRestrictions() {
    if ($this->restrictions) {
      return $this->restrictions;
    }

    // Parse the allowed HTML setting, and gradually make the list of allowed
    // tags more specific.
    $restrictions = ['allowed' => []];

    // Make all the tags self-closing, so they will be parsed into direct
    // children of the body tag in the DomDocument.
    $html = str_replace('>', ' />', $this->settings['allowed_html']);
    // Protect any trailing * characters in attribute names, since DomDocument
    // strips them as invalid.
    // cSpell:disable-next-line
    $star_protector = '__zqh6vxfbk3cg__';
    $html = str_replace('*', $star_protector, $html);
    $body_child_nodes = Html::load($html)->getElementsByTagName('body')->item(0)->childNodes;

    foreach ($body_child_nodes as $node) {
      if ($node->nodeType !== XML_ELEMENT_NODE) {
        // Skip the empty text nodes inside tags.
        continue;
      }
      $tag = $node->tagName;

      // All attributes are already allowed on this tag, this is the most
      // permissive configuration, no additional processing is required.
      if (isset($restrictions['allowed'][$tag]) && $restrictions['allowed'][$tag] === TRUE) {
        continue;
      }

      if ($node->hasAttributes()) {
        // If the tag is not yet present, prepare to add attribute restrictions.
        // Otherwise, check if a more restrictive configuration (FALSE, meaning
        // no attributes were allowed) is present: then override the existing
        // value to prepare to add attribute restrictions.
        if (!isset($restrictions['allowed'][$tag]) || $restrictions['allowed'][$tag] === FALSE) {
          $restrictions['allowed'][$tag] = [];
        }

        // Iterate over any attributes, and mark them as allowed.
        foreach ($node->attributes as $name => $attribute) {
          // Only add specific attribute values if all values are not already
          // allowed.
          if (isset($restrictions['allowed'][$tag][$name]) && $restrictions['allowed'][$tag][$name] === TRUE) {
            continue;
          }
          // Put back any trailing * on wildcard attribute name.
          $name = str_replace($star_protector, '*', $name);

          // Put back any trailing * on wildcard attribute value and parse out
          // the allowed attribute values.
          $allowed_attribute_values = preg_split('/\s+/', str_replace($star_protector, '*', $attribute->value), -1, PREG_SPLIT_NO_EMPTY);

          // Sanitize the attribute value: it lists the allowed attribute values
          // but one allowed attribute value that some may be tempted to use
          // is specifically nonsensical: the asterisk. A prefix is required for
          // allowed attribute values with a wildcard. A wildcard by itself
          // would mean allowing all possible attribute values. But in that
          // case, one would not specify an attribute value at all.
          $allowed_attribute_values = array_filter($allowed_attribute_values, function ($value) {
            return $value !== '*';
          });

          if (empty($allowed_attribute_values)) {
            // If the value is the empty string all values are allowed.
            $restrictions['allowed'][$tag][$name] = TRUE;
          }
          else {
            // A non-empty attribute value is assigned, mark each of the
            // specified attribute values as allowed.
            foreach ($allowed_attribute_values as $value) {
              $restrictions['allowed'][$tag][$name][$value] = TRUE;
            }
          }
        }
      }

      if (empty($restrictions['allowed'][$tag])) {
        // Mark the tag as allowed, but with no attributes allowed.
        $restrictions['allowed'][$tag] = FALSE;
      }
    }

    // The 'style' and 'on*' ('onClick' etc.) attributes are always forbidden,
    // and are removed by Xss::filter().
    // The 'lang', and 'dir' attributes apply to all elements and are always
    // allowed. The list of allowed values for the 'dir' attribute is enforced
    // by self::filterAttributes(). Note that those two attributes are in the
    // short list of globally usable attributes in HTML5. They are always
    // allowed since the correct values of lang and dir may only be known to
    // the content author. Of the other global attributes, they are not usually
    // added by hand to content, and especially the class attribute can have
    // undesired visual effects by allowing content authors to apply any
    // available style, so specific values should be explicitly allowed.
    // @see http://www.w3.org/TR/html5/dom.html#global-attributes
    $restrictions['allowed']['*'] = [
      'style' => FALSE,
      'on*' => FALSE,
      'lang' => FALSE,
      'dir' => FALSE,
    ];
    // Save this calculated result for re-use.
    $this->restrictions = $restrictions;

    return $restrictions;
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    global $base_url;

    if (!($allowed_html = $this->settings['allowed_html'])) {
      return;
    }
    $output = $this->t('Allowed HTML tags: @tags', ['@tags' => $allowed_html]);
    if (!$long) {
      return $output;
    }

    $output = '<p>' . $output . '</p>';
    if (!$this->settings['filter_html_help']) {
      return $output;
    }

    $output .= '<p>' . $this->t('This site allows HTML content. While learning all of HTML may feel intimidating, learning how to use a very small number of the most basic HTML "tags" is very easy. This table provides examples for each tag that is enabled on this site.') . '</p>';
    $output .= '<p>' . $this->t('For more information see the <a href=":html-specifications">HTML Living Standard</a> or use your favorite search engine to find other sites that explain HTML.', [':html-specifications' => 'https://html.spec.whatwg.org/']) . '</p>';
    $tips = [
      'a' => [$this->t('Anchors are used to make links to other pages.'), '<a href="' . $base_url . '">' . Html::escape(\Drupal::config('system.site')->get('name')) . '</a>'],
      'br' => [$this->t('By default line break tags are automatically added, so use this tag to add additional ones. Use of this tag is different because it is not used with an open/close pair like all the others. Use the extra " /" inside the tag to maintain XHTML 1.0 compatibility'), $this->t('Text with <br />line break')],
      'p' => [$this->t('By default paragraph tags are automatically added, so use this tag to add additional ones.'), '<p>' . $this->t('Paragraph one.') . '</p> <p>' . $this->t('Paragraph two.') . '</p>'],
      'strong' => [$this->t('Strong', [], ['context' => 'Font weight']), '<strong>' . $this->t('Strong', [], ['context' => 'Font weight']) . '</strong>'],
      'em' => [$this->t('Emphasized'), '<em>' . $this->t('Emphasized') . '</em>'],
      'cite' => [$this->t('Cited'), '<cite>' . $this->t('Cited') . '</cite>'],
      'code' => [$this->t('Coded text used to show programming source code'), '<code>' . $this->t('Coded') . '</code>'],
      'b' => [$this->t('Bolded'), '<b>' . $this->t('Bolded') . '</b>'],
      'u' => [$this->t('Underlined'), '<u>' . $this->t('Underlined') . '</u>'],
      'i' => [$this->t('Italicized'), '<i>' . $this->t('Italicized') . '</i>'],
      'sup' => [$this->t('Superscripted'), $this->t('<sup>Super</sup>scripted')],
      'sub' => [$this->t('Subscripted'), $this->t('<sub>Sub</sub>scripted')],
      'pre' => [$this->t('Preformatted'), '<pre>' . $this->t('Preformatted') . '</pre>'],
      'abbr' => [$this->t('Abbreviation'), $this->t('<abbr title="Abbreviation">Abbrev.</abbr>')],
      'acronym' => [$this->t('Acronym'), $this->t('<acronym title="Three-Letter Acronym">TLA</acronym>')],
      'blockquote' => [$this->t('Block quoted'), '<blockquote>' . $this->t('Block quoted') . '</blockquote>'],
      'q' => [$this->t('Quoted inline'), '<q>' . $this->t('Quoted inline') . '</q>'],
      // Assumes and describes tr, td, th.
      'table' => [$this->t('Table'), '<table> <tr><th>' . $this->t('Table header') . '</th></tr> <tr><td>' . $this->t('Table cell') . '</td></tr> </table>'],
      'tr' => NULL,
      'td' => NULL,
      'th' => NULL,
      'del' => [$this->t('Deleted'), '<del>' . $this->t('Deleted') . '</del>'],
      'ins' => [$this->t('Inserted'), '<ins>' . $this->t('Inserted') . '</ins>'],
       // Assumes and describes li.
      'ol' => [$this->t('Ordered list - use the &lt;li&gt; to begin each list item'), '<ol> <li>' . $this->t('First item') . '</li> <li>' . $this->t('Second item') . '</li> </ol>'],
      'ul' => [$this->t('Unordered list - use the &lt;li&gt; to begin each list item'), '<ul> <li>' . $this->t('First item') . '</li> <li>' . $this->t('Second item') . '</li> </ul>'],
      'li' => NULL,
      // Assumes and describes dt and dd.
      'dl' => [$this->t('Definition lists are similar to other HTML lists. &lt;dl&gt; begins the definition list, &lt;dt&gt; begins the definition term and &lt;dd&gt; begins the definition description.'), '<dl> <dt>' . $this->t('First term') . '</dt> <dd>' . $this->t('First definition') . '</dd> <dt>' . $this->t('Second term') . '</dt> <dd>' . $this->t('Second definition') . '</dd> </dl>'],
      'dt' => NULL,
      'dd' => NULL,
      'h1' => [$this->t('Heading'), '<h1>' . $this->t('Title') . '</h1>'],
      'h2' => [$this->t('Heading'), '<h2>' . $this->t('Subtitle') . '</h2>'],
      'h3' => [$this->t('Heading'), '<h3>' . $this->t('Subtitle three') . '</h3>'],
      'h4' => [$this->t('Heading'), '<h4>' . $this->t('Subtitle four') . '</h4>'],
      'h5' => [$this->t('Heading'), '<h5>' . $this->t('Subtitle five') . '</h5>'],
      'h6' => [$this->t('Heading'), '<h6>' . $this->t('Subtitle six') . '</h6>'],
    ];
    $header = [$this->t('Tag Description'), $this->t('You Type'), $this->t('You Get')];
    preg_match_all('/<([a-z0-9]+)[^a-z0-9]/i', $allowed_html, $out);
    foreach ($out[1] as $tag) {
      if (!empty($tips[$tag])) {
        $rows[] = [
          ['data' => $tips[$tag][0], 'class' => ['description']],
          // The markup must be escaped because this is the example code for the
          // user.
          [
            'data' => [
              '#prefix' => '<code>',
              '#plain_text' => $tips[$tag][1],
              '#suffix' => '</code>',
            ],
            'class' => ['type'],
          ],
          // The markup must not be escaped because this is the example output
          // for the user.
          ['data' => ['#markup' => $tips[$tag][1]], 'class' => ['get']],
        ];
      }
      else {
        $rows[] = [
          ['data' => $this->t('No help provided for tag %tag.', ['%tag' => $tag]), 'class' => ['description'], 'colspan' => 3],
        ];
      }
    }
    $table = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];
    $output .= \Drupal::service('renderer')->render($table);

    $output .= '<p>' . $this->t('Most unusual characters can be directly entered without any problems.') . '</p>';
    $output .= '<p>' . $this->t('If you do encounter problems, try using HTML character entities. A common example looks like &amp;amp; for an ampersand &amp; character. For a full list of entities see HTML\'s <a href=":html-entities">entities</a> page. Some of the available characters include:', [':html-entities' => 'http://www.w3.org/TR/html4/sgml/entities.html']) . '</p>';

    $entities = [
      [$this->t('Ampersand'), '&amp;'],
      [$this->t('Greater than'), '&gt;'],
      [$this->t('Less than'), '&lt;'],
      [$this->t('Quotation mark'), '&quot;'],
    ];
    $header = [$this->t('Character Description'), $this->t('You Type'), $this->t('You Get')];
    unset($rows);
    foreach ($entities as $entity) {
      $rows[] = [
        ['data' => $entity[0], 'class' => ['description']],
        // The markup must be escaped because this is the example code for the
        // user.
        [
          'data' => [
            '#prefix' => '<code>',
            '#plain_text' => $entity[1],
            '#suffix' => '</code>',
          ],
          'class' => ['type'],
        ],
        // The markup must not be escaped because this is the example output
        // for the user.
        [
          'data' => ['#markup' => $entity[1]],
          'class' => ['get'],
        ],
      ];
    }
    $table = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];
    $output .= \Drupal::service('renderer')->render($table);
    return $output;
  }

}
