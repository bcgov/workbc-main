(function ($, Drupal, once) {
  ("use strict");

  let initialize = function (containerJquery) {
    let container = $(containerJquery);
    let initialCount = container.data('static-load-more-initial');
    let moreText = container.data('static-more-text');
    let items = container.children('[data-static-load-more-items]').first().children();
    let trigger = container.find('[data-static-load-more-trigger]').first();

    // Hide the extra items: Either the ones outside the initial count, ...
    if (Number.isInteger(initialCount)) {
      items.slice(initialCount).hide();
    }
    // ... or the ones with "illustrative" flag = 0.
    else {
      items.filter(`[data-${initialCount}='0']`).hide();
    }

    trigger.on('click', function() { loadMore(container) });

    // If moreText parameter is populated, update link text, otherwise default to using
    // the text between the anchor tags.  ie: "<a ...>Show more</a>"
    if (moreText) {
      trigger.text(moreText)
    }

    container.show();
  };

  let loadMore = function (containerJquery) {
    let container = $(containerJquery);
    let stepCount = container.data('static-load-more-step');
    let lessText = container.data('static-less-text');
    let hiddenItems = container.children('[data-static-load-more-items]').first().children(':hidden');

    hiddenItems.slice(0, stepCount).show();
    let trigger = container.find('[data-static-load-more-trigger]').first();

    // If lessText is not set, hide the 'show less' link
    if (hiddenItems.length <= stepCount && ! lessText) {
      trigger.hide();
    }

    // If lessText is populated, display the 'show less' link text
    if (hiddenItems.length <= stepCount && lessText) {
      trigger.on('click', function() { showLess(container) });
      trigger.text(lessText);
    }
  }

  let showLess = function (containerJquery) {
    let container = $(containerJquery);
    let moreText = container.data('static-more-text');
    let initialCount = container.data('static-load-more-initial');
    let trigger = container.find('[data-static-load-more-trigger]').first();
    let items = container.children('[data-static-load-more-items]').first().children();

    // Hide the extra items: Either the ones outside the initial count, ...
    if (Number.isInteger(initialCount)) {
      items.slice(initialCount).hide();
    }
    // ... or the ones with "illustrative" flag = 0.
    else {
      items.filter(`[data-${initialCount}='0']`).hide();
    }

    container.show();
    trigger.on('click', function() { loadMore(container) });
    trigger.text(moreText)
  }

  Drupal.behaviors.initStaticLoadMoreBehavior = {
    attach: function (context, settings) {
      once('initStaticLoadMoreBehavior', '[data-static-load-more-container]', context).forEach(initialize);
    },
  };

})(jQuery, Drupal, once);
