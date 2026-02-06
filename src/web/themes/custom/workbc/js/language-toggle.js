(function (Drupal, $, once) {
	Drupal.behaviors.languageToggle = {
    attach: function (context, settings) {
      // FIXME: Copied from src/web/modules/contrib/gtranslate/js/dropdown.js
      // because I couldn't figure out how to trigger the loading of the library. It should be triggerable as per:
      // document.querySelectorAll(u_class).forEach(function(e){e.addEventListener('pointerenter',load_tlib)});
      function load_tlib(){if(!window.gt_translate_script){window.gt_translate_script=document.createElement('script');gt_translate_script.src='https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit2';document.body.appendChild(gt_translate_script);}}

      const cookie_lang = document.cookie
        .split("; ")
        .find((row) => row.startsWith("googtrans="))
        ?.split("=")[1];
      let isFrench = cookie_lang === '/en/fr';
      $('.language-toggle input', context).prop('checked', isFrench);
      $(once('languageToggle', '.language-toggle', context)).on('click', () => {
        const checked = $('.language-toggle input', context).is(':checked');
        if (checked !== isFrench) {
          load_tlib();
          isFrench = checked;
          const lang = isFrench ? 'en|fr' : 'en|en';
          $('.gt_selector', context).val(lang);
          window.doGTranslate(lang);
        }
      }).on('keydown', (e) => {
        if (13 === e.keyCode) {
          const $input = $('.language-toggle input', context);
          $input.prop('checked', !$input.prop('checked'));
          $(e.target).trigger('click');
        }
      });

      $(once('languageToggle', '.gt_selector', context)).on('change', (e) => {
        const lang = $(e.target).val();
        isFrench = lang === 'en|fr';
        $('.language-toggle input', context).prop('checked', isFrench);
      });
    }
  }
})(Drupal, jQuery, once);
