(function ($, Drupal, once) {
    ("use strict");
  
    const initScrollbooster = () => {
        // this assumes/requires that ScrollBooster library has already been imported
        new ScrollBooster({
          viewport: document.querySelector(".scroll-container"),
          content: document.querySelector(".scroll"),
          direction: "horizontal",
          scrollMode: "transform"
        });
    
        console.log("init scroll");
    };
    
    Drupal.behaviors.scroll_booster = {
      attach: function (context, settings) {
        // the second parameter must be a selector specific to the content this script applies to, to ensure it's loaded after the content in the case the content is lazy loaded by Drupal
        once('scroll_booster', '.scroll-container', context).forEach(initScrollbooster);
        console.log("once");
      },
    };
  
  })(jQuery, Drupal, once);