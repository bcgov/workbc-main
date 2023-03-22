(function ($, Drupal) {
    ("use strict");

    const scrollToTop = () => {
        document.body.scrollTo({top: 0, behavior: 'smooth'});
    };

    const initScrollToTopTrigger = () => {
        var trigger = injectFixedButton();
        attachScrollListener(trigger);
    };

    const injectFixedButton = () => {
        var container = document.createElement('div');
        container.classList.add('back-to-top');
        container.setAttribute('role', 'none');
        document.body.appendChild(container);

        var label = document.createElement('label');
        label.textContent = 'Back to top';
        label.classList.add('visually-hidden');
        container.appendChild(label);

        var trigger = document.createElement('button');
        trigger.textContent = 'Back to top';
        trigger.classList.add('back-to-top__trigger', 'btn', 'btn-prefix-chevron-up');
        trigger.setAttribute('aria-label', 'Back to top');
        trigger.addEventListener('click', scrollToTop);
        container.appendChild(trigger);

        return trigger;
    };

    const attachScrollListener = (triggerElem) => {

        var onScrollBehavior = function() {
          var y = document.body.scrollTop;
          if (y >= 150) {
            triggerElem.classList.add('active');
          } else {
            triggerElem.classList.remove('active');
          }
        };

        document.body.addEventListener("scroll", onScrollBehavior);
    };

    Drupal.behaviors.back_to_top = {
      attach: function (context, settings) {
        $(document, context).once('back_to_top').each(initScrollToTopTrigger);
      },
    };

  })(jQuery, Drupal);