(function ($, Drupal, once) {
    "use strict";
    Drupal.behaviors.rangeSlider = {
      attach: function (context, settings) {
        once('rangeSlider', '#annual-salary', context).forEach(function (element) {
          const $element = $(element);
  
          $element.jRange({
            from: 10000,
            to: 140000,
            step: 1000,
            format: '%s',
            width: 300,
            showLabels: true,
            isRange: true,
            onstatechange: function (value) {
              const values = value.split(',');
              const minValue = values[0];
              const maxValue = values[1];
  
              $('fieldset[data-drupal-selector="edit-annual-salary-wrapper"] input[name="annual_salary[min]"]', context).val(minValue);
              $('fieldset[data-drupal-selector="edit-annual-salary-wrapper"] input[name="annual_salary[max]"]', context).val(maxValue);
            }
          });
  
          // Set the default range AFTER jRange is initialized
          const defaultMin = 1000;
          const defaultMax = 1400000;
          const defaultValue = `${defaultMin},${defaultMax}`;
  
          $element.jRange('setValue', defaultValue);
  
          // Update the input fields manually to reflect default values
          $('fieldset[data-drupal-selector="edit-annual-salary-wrapper"] input[name="annual_salary[min]"]', context).val('');
          $('fieldset[data-drupal-selector="edit-annual-salary-wrapper"] input[name="annual_salary[max]"]', context).val('');
        });
      },
    };
})(jQuery, Drupal, once);
  