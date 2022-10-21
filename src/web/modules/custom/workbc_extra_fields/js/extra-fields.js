(function ($) {
  ("use strict");


  // Scroll detection for Banner Top offset.
  Drupal.behaviors.employmentmonths = {
    attach: function (context, settings) {
      var currentUrl = window.location.href.split('?')[0];

      $('#employment-months').change( function() {
        var value = $(this).val();
        var parameters = value.split("_");

        var redirectUrl = currentUrl+'?month='+parameters[0]+'&year='+parameters[1];
        window.location.href = redirectUrl;
        console.log($(this).val());
      });
    },
  };



})(jQuery);
