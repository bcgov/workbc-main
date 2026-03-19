/**
 * This language toggle between English and French invokes Google Translate to do its job
 * UNLESS the Drupal settings include a `languageToggle` key, in which case it uses those settings to
 * redirect between English page and French page.
 */
(function (Drupal, $, once) {
	Drupal.behaviors.languageToggle = {
    attach: function (context, settings) {
      once('languageToggle', 'html', context).forEach(function() {
        window.addEventListener('load', pageLoad);
        window.addEventListener('unload', () => {});
      });

      once('languageToggle', 'body.search-and-prepare-jobfind-jobs', context).forEach(function() {
        window.addEventListener('load', jobFindPageChanges);
        window.addEventListener('hashchange', jobFindPageChanges);
      });

      // Get the Google Translate cookie.
      const googtrans = document.cookie
        .split("; ")
        .find((row) => row.startsWith("googtrans="))
        ?.split("=")[1];

      // Determine the current page's language from these sources in order:
      // * settings.languageToggle.isFrench (from Drupal backend)
      // * sessionStorage(KEY_LANGUAGE_TOGGLE) (from this script)
      // * googtrans cookie (from Google Translate)
      const KEY_LANGUAGE_TOGGLE = 'WorkBC.languageToggle';
      let isFrench =
        settings.languageToggle?.isFrench === true ||
        window.sessionStorage.getItem(KEY_LANGUAGE_TOGGLE) === 'fr' ||
        googtrans === '/en/fr';
      window.sessionStorage.setItem(KEY_LANGUAGE_TOGGLE, isFrench ? 'fr' : 'en');

      // Handle clicking our toggle.
      $(once('languageToggle', '.language-toggle', context)).on('click', () => {
        const checked = $('.language-toggle input', context).is(':checked');
        window.sessionStorage.setItem(KEY_LANGUAGE_TOGGLE, checked ? 'fr' : 'en');

        if (checked !== isFrench) {
          if ('languageToggle' in settings) {
            window.location = settings.languageToggle.url;
            return;
          }
          else {
            isFrench = checked;
            doGTranslate();
          }
        }
      }).on('keydown', (e) => {
        if (13 === e.keyCode) {
          const $input = $('.language-toggle input', context);
          $input.prop('checked', !$input.prop('checked'));
          $(e.target).trigger('click');
        }
      });

      // Handle selecting the Google Translate dropdown.
      $(once('languageToggle', '.gt_selector', context)).on('change', (e) => {
        const lang = $(e.target).val();
        if ('languageToggle' in settings) {
          if (
            (lang === 'en|fr' && !settings.languageToggle.isFrench) ||
            (lang === 'en|en' && settings.languageToggle.isFrench)
          ) {
            window.sessionStorage.setItem(KEY_LANGUAGE_TOGGLE, lang.split('|')[1]);
            window.location = settings.languageToggle.url;
            return;
          }
        }
        isFrench = lang === 'en|fr';
        window.sessionStorage.setItem(KEY_LANGUAGE_TOGGLE, isFrench ? 'fr' : 'en');
        $('.language-toggle input', context).prop('checked', isFrench);
      });

      // Handle initial page load.
      function pageLoad() {
        $('.language-toggle input', context).prop('checked', isFrench);

        if ('languageToggle' in settings) {
          // Redirect to correct language if needed.
          if (isFrench !== settings.languageToggle.isFrench) {
            window.location = settings.languageToggle.url;
            return;
          }

          // Reset the Google Translate widget.
          doGTranslate(true);
        }
        else {
          // Set the Google Translate widget.
          doGTranslate();
        }
      }

      // Handle special case for Job Board detail screen.
      function jobFindPageChanges() {
        if (window.location.hash.startsWith('#/job-details/')) {
          doGTranslate(true);

          // Sync the Job Board language toggle with ours.
          waitForEl('app-job-detail #job-language', () => {
            if (isFrench) $('app-job-detail #job-language').click();

            $(once('languageToggle', 'app-job-detail #job-language')).on('change', (e) => {
              isFrench = $(e.target).prop('checked');
              window.sessionStorage.setItem(KEY_LANGUAGE_TOGGLE, isFrench ? 'fr' : 'en');
              const $input = $('.language-toggle input', context);
              $input.prop('checked', !$input.prop('checked'));
            });
          });
        }
        else {
          doGTranslate();
        }
      }

      // FIXME: Copied from src/web/modules/contrib/gtranslate/js/dropdown.js
      // because I couldn't figure out how to trigger the loading of the library. It should be triggerable as per:
      // document.querySelectorAll(u_class).forEach(function(e){e.addEventListener('pointerenter',load_tlib)});
      function load_tlib(){if(!window.gt_translate_script){window.gt_translate_script=document.createElement('script');gt_translate_script.src='https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit2';document.body.appendChild(gt_translate_script);}}

      // Activate / reset Google Translate.
      function doGTranslate(reset = false) {
          load_tlib();
          const lang = isFrench && !reset ? 'en|fr' : 'en|en';
          $('.gt_selector', context).val(lang);
          window.doGTranslate(lang);
      }

      // Wait for an element to be available before calling a function.
      function waitForEl (selector, callback) {
        if ($(selector).length) {
          callback();
        } else {
          setTimeout(function() {
            waitForEl(selector, callback);
          }, 100);
        }
      }
    }
  }
})(Drupal, jQuery, once);
