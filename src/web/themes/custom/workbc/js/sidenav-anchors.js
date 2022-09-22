(function ($, Drupal, once) {
  ("use strict");

  let linkList = jQuery('ul.basic-page-left-nav-links');

  let configureHeadingAnchor = function (index, element) {
    var anchorid = "sideNavAnchorId_" + index;
    var anchorText = jQuery(element).text();

    $(this).wrapInner('<a id="' + anchorid + '" />');
    linkList.append('<li><a href="#' + anchorid + '">' + anchorText + '</a></li>');
  };

  let initSidenavAnchors = function() {
    let article = jQuery('article.page-format--sidenav');
    let headings = article.find('h2');

    headings.each(configureHeadingAnchor);
  };

  Drupal.behaviors.sidenavAnchors = {
    attach: function (context, settings) {
      // the second parameter must be a selector specific to the content this script applies to, to ensure it's loaded after the content in the case the content is lazy loaded by Drupal
      once('sidenavAnchors', '.basic-page-left-nav-wrapper', context).forEach(initSidenavAnchors);
    },
  };

})(jQuery, Drupal, once);