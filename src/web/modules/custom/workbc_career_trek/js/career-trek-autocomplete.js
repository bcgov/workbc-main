(function ($, Drupal) {
    Drupal.behaviors.searchAutocomplete = {
      attach(context) {
        once('career_trek', '.plan-careercareer-trek-videos input[name="search_api_fulltext"]', context)
          .forEach(function (input) {
            $(input).autocomplete({
              minLength: 2,
              source: function (request, response) {
                $.getJSON('/career-trek/search-autocomplete/suggestions', { q: request.term }, function (data) {
                  response($.map(data, function (item) {
                    return {
                      label: item.value,
                      value: item.value,
                      url: item.url
                    };
                  }));
                });
              },
              select: function (event, ui) {
                window.location.href = ui.item.url;
                return false;
              }
            });
          });
      }
    };
  })(jQuery, Drupal);
  