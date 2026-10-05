(function ($, Drupal, once) {
  Drupal.behaviors.hooPagePosition = {
    attach: function (context, settings) {
      const viewSubmit = sessionStorage.getItem('hooExposedFilterSubmit') === 'true';

      if (viewSubmit) {
        const element = document.getElementById("block-workbc-views-block-high-opportunity-occupations-2-block-1");
        var elementTop = element.getBoundingClientRect().top - 125;
        if (settings.isMobile) {
          elementTop = element.getBoundingClientRect().top + 35;
        }

        window.scrollTo({
          behaviour: "smooth",
          top: elementTop,
        });

        sessionStorage.setItem('hooExposedFilterSubmit', false);
      }

      once('highopportunityoccupations', '.hoo-content', context).forEach(function() {
        $('#edit-submit-high-opportunity-occupations-2').on('click' , function() {
          sessionStorage.setItem('hooExposedFilterSubmit', true);
        });
      });

    }
  };

})(jQuery, Drupal, once);
