(function ($, Drupal, once) {
  ("use strict");

  let initSidenavAnchors = function(wrapper) {
    let article = $('article.page-format--sidenav');
    let headings = article.find(".node-page-content h2").not(".node-page-content .on-this-page h2");

    headings.each(function (index, element) {
      var anchorid = "sideNavAnchorId_" + index;
      var anchorText = $(element).text();
      let linkList = $('ul.page-left-nav__links', wrapper);

      if (!$('#' + anchorid).length) {
        $(this).append('<a class="sidenav-anchor" id="' + anchorid + '" />');
      }
      linkList.append('<li><a href="#' + anchorid + '">' + anchorText + '</a></li>');
    });
  };

  Drupal.behaviors.sidenavAnchors = {
    attach: function (context, settings) {
      // the second parameter must be a selector specific to the content this script applies to, to ensure it's loaded after the content in the case the content is lazy loaded by Drupal
      once('sidenavAnchors', '.page-left-nav-wrapper', context).forEach(initSidenavAnchors);
    },
  };

})(jQuery, Drupal, once);
