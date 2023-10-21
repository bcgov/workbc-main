
(function ($, Drupal, once) {
	Drupal.behaviors.mobilechosen = {
    attach: function (context, settings) {

      once('mobilechosen', '.chosen-search-input', context).forEach(function() {

        console.log("here");
        var element = context.querySelector(".chosen-search-input");
        console.log(element);
        element.placeholder = "wHat";


      });
    }
  }
})(jQuery, Drupal, once);
