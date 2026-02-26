/**
 * This language toggle between English and French invokes Google Translate to do its job
 * UNLESS the Drupal settings include a `languageToggle` key, in which case it uses those settings to
 * redirect between English page and French page.
 */
(function (Drupal, $, once) {
	Drupal.behaviors.languageToggle = {
    attach: function (context, settings) {
      once('languageToggle', 'body.search-and-prepare-jobfind-jobs', context).forEach(function() {
        window.addEventListener('load', jobFindPageChanges);
        window.addEventListener('hashchange', jobFindPageChanges);
      });

      // FIXME: Copied from src/web/modules/contrib/gtranslate/js/dropdown.js
      // because I couldn't figure out how to trigger the loading of the library. It should be triggerable as per:
      // document.querySelectorAll(u_class).forEach(function(e){e.addEventListener('pointerenter',load_tlib)});
      function load_tlib(){if(!window.gt_translate_script){window.gt_translate_script=document.createElement('script');gt_translate_script.src='https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit2';document.body.appendChild(gt_translate_script);}}

      const cookie_lang = document.cookie
        .split("; ")
        .find((row) => row.startsWith("googtrans="))
        ?.split("=")[1];
      let isFrench = 'languageToggle' in settings ? settings.languageToggle.isFrench : cookie_lang === '/en/fr';
      $('.language-toggle input', context).prop('checked', isFrench);
      $(once('languageToggle', '.language-toggle', context)).on('click', () => {
        const checked = $('.language-toggle input', context).is(':checked');
        if (checked !== isFrench) {
          if ('languageToggle' in settings) {
            window.location = settings.languageToggle.url;
          }
          else {
            load_tlib();
            isFrench = checked;
            const lang = isFrench ? 'en|fr' : 'en|en';
            $('.gt_selector', context).val(lang);
            window.doGTranslate(lang);
          }
        }
      }).on('keydown', (e) => {
        if (13 === e.keyCode) {
          const $input = $('.language-toggle input', context);
          $input.prop('checked', !$input.prop('checked'));
          $(e.target).trigger('click');
        }
      });

      $(once('languageToggle', '.gt_selector', context)).on('change', (e) => {
        if (!('languageToggle' in settings)) {
          const lang = $(e.target).val();
          isFrench = lang === 'en|fr';
          $('.language-toggle input', context).prop('checked', isFrench);
        }
      });

      function jobFindPageChanges() {
        if (window.location.hash.startsWith('#/job-details/')) {
          $('.language-toggle', context).hide();
        }
        else {
          $('.language-toggle', context).show();
        }
      }
    }
  }
})(Drupal, jQuery, once);
