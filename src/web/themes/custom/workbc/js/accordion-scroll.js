
(function ($, Drupal, once) {
	Drupal.behaviors.accordion = {
    attach: function (context, settings) {

      const delay = 200;

      once('accordionscroll', 'html', context).forEach(function() {
        $('.accordion-header').on('click', function() {
          const top = this.getBoundingClientRect().top;
          const height = this.getBoundingClientRect().height;
          setTimeout(() => {
            if (this.getBoundingClientRect().top < 85) {
              window.scrollTo({ top: window.scrollY - (top - this.getBoundingClientRect().top) - height, behavior: 'smooth' });
            }
          }, delay);

        });
      });

    }
  }
})(jQuery, Drupal, once);
