
(function ($, Drupal, once) {
  "use strict";

  Drupal.behaviors.rangeSlider = {
    attach: function (context, settings) {
      once('rangeSlider', '#annual-salary', context).forEach(initializeSlider);

      $('.responsive-filter-video-btn', context).on('click', function () {
        setTimeout(resizeSlider, 100);
      });

      function initializeSlider(element) {
        const $element = $(element);
        const parentWidth = $('details[data-drupal-selector="edit-annual-salary-collapsible"]').width() - 15;
        initializeRangeSlider($element, parentWidth, context);
      }

      function resizeSlider() {
        const newWidth = $('details[data-drupal-selector="edit-annual-salary-collapsible"]').width();
        initializeRangeSlider($('#annual-salary'), newWidth, context);
      }

      function initializeRangeSlider($element, width, context) {
        once('rangeSliderInit', $element, context).forEach(element => {
          $(element).jRange({
            from: 10000,
            to: 140000,
            step: 1000,
            width: (width > 0 && width !== "") ? width : 300,
            format: function (value) {
              const val = parseInt(value);
              return (val === 140000) ? '140000+' : val;
            },
            showLabels: true,
            isRange: true,
            onstatechange: function (value) {
              const [minValue, maxValue] = value.split(',');

              if(value != "10000,10000") {
                const $annualSalaryOp = $('.plan-careercareer-trek-videos .view-career-trek-video-library .career-videos-filters .salary-range-search select[name="annual_salary_op"]');
                if (maxValue == 140000) {
                  $annualSalaryOp.val('>');
                  if(minValue != 10000) {
                    $('fieldset[data-drupal-selector="edit-annual-salary-wrapper"] input[name="annual_salary[value]"]').val(minValue);
                  }else{
                    $('fieldset[data-drupal-selector="edit-annual-salary-wrapper"] input[name="annual_salary[value]"]').val('');
                  }
                  $(`fieldset[data-drupal-selector="edit-annual-salary-wrapper"] input[name="annual_salary[min]"]`).val('');
                  $('fieldset[data-drupal-selector="edit-annual-salary-wrapper"] input[name="annual_salary[max]"]').val('');
                } else {
                  $annualSalaryOp.val('between');
                  $(`fieldset[data-drupal-selector="edit-annual-salary-wrapper"] input[name="annual_salary[min]"]`).val(minValue);
                  $('fieldset[data-drupal-selector="edit-annual-salary-wrapper"] input[name="annual_salary[max]"]').val(maxValue);

                }
              }
            }
          });
          $(document).ajaxComplete(function () {
            const $minInput = $('fieldset[data-drupal-selector="edit-annual-salary-wrapper"] input[name="annual_salary[min]"]');
            const $maxInput = $('fieldset[data-drupal-selector="edit-annual-salary-wrapper"] input[name="annual_salary[max]"]');
            const $valueInput = $('fieldset[data-drupal-selector="edit-annual-salary-wrapper"] input[name="annual_salary[value]"]');
            const min = $minInput.val() || $valueInput.val() || 10000;
            const max = $maxInput.val() || 140000;
            const $salaryOp = $('.plan-careercareer-trek-videos .view-career-trek-video-library .career-videos-filters .salary-range-search select[name="annual_salary_op"]');
            if (min && max) {
              $(`#annual-salary`).jRange('setValue', `${min},${max}`);
              
            }
  
            if ($valueInput.val()) {
              $salaryOp.val('>');
              $minInput.val(min);
              $maxInput.val(max);
            } else {
              $salaryOp.val('between');
              $('fieldset[data-drupal-selector="edit-annual-salary-wrapper"] input[name^="annual_salary"]').val('');
            }
            if(max != 140000) {
              $maxInput.val(max);
            }
            if(min != 10000) {
              $minInput.val(min);
            }
          });
          $(window).on('load', function() {
            $element.jRange('setValue', '10000,140000');
            $('fieldset[data-drupal-selector="edit-annual-salary-wrapper"] input[name^="annual_salary"]').val('');
          });
        });
        
      }
    }
  };
})(jQuery, Drupal, once);
