
(function ($, Drupal, once) {
  "use strict";

  Drupal.behaviors.rangeSlider = {
    attach: function (context, settings) {
      // Only initialize ONCE on attach.
      once('rangeSlider', '#annual-salary', context).forEach(initializeSlider);

      // Re-initialize the slider on window resize
      // Re-initialize the slider on window resize
      $(window).on('resize', function () {
        const $slider = $('#annual-salary');
        if($('.salary-range-search details').width()){
          const parentWidth = $('.salary-range-search details').width() - 15;
          $('.slider-container').remove();
          $('#annual-salary').remove();
          $('fieldset[data-drupal-selector="edit-annual-salary-wrapper"] .fieldset-wrapper').prepend('<div id="annual-salary"></div>');
          setTimeout(resizeSlider, 100);
        }

      });
      $('.responsive-filter-video-btn', context).on('click', function () {
        setTimeout(resizeSlider, 100);
      });

      // $(window).on('resize', function () {
      //   clearTimeout(window.resizingSlider);
      //   window.resizingSlider = setTimeout(resizeSlider, 200);
      // });

      function initializeSlider(element) {
        const $element = $(element);
        if($('.salary-range-search details').width()){
          const parentWidth = $('.salary-range-search details').width() - 15;
          initializeRangeSlider($element, parentWidth, context);
        }
      }

      // Fix: Do NOT use once() in resizeSlider, just update the slider directly.
      function resizeSlider() {
        const newWidth = $('.salary-range-search details').width() - 15;
        const $slider = $('#annual-salary');
        // Destroy and re-initialize the slider to avoid "once" issues.
        // if ($slider.data('jRange')) {
        //   $slider.data('jRange').destroy();
        //   $slider.removeData('jRange');
        //   $slider.empty();
        // }
        initializeRangeSlider($slider, newWidth, context, true);
        const $minInput = $('fieldset[data-drupal-selector="edit-annual-salary-wrapper"] input[name="annual_salary[min]"]');
        const $maxInput = $('fieldset[data-drupal-selector="edit-annual-salary-wrapper"] input[name="annual_salary[max]"]');
        const $valueInput = $('fieldset[data-drupal-selector="edit-annual-salary-wrapper"] input[name="annual_salary[value]"]');
        if(!$minInput.val() && !$maxInput.val() && !$valueInput.val()) {
          $slider.jRange('setValue', '10000,140000');
        }
        // Run the ajaxComplete logic ONCE here instead of in initializeRangeSlider
        (function runAjaxCompleteLogicOnce() {
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
            if(min == 10000) {
              $minInput.val(min);
            }
          }
          if(min != 10000) {
            $minInput.val(min);
          }
        })();
      }

      // Add a flag to skip once() if re-initializing
      function initializeRangeSlider($element, width, context, force) {
        // If force is true, skip once() and always re-initialize
        const elements = force ? $element.toArray() : once('rangeSliderInit', $element, context);
        elements.forEach(element => {

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
          if(!force) {
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
                if(min == 10000) {
                  $minInput.val(min);
                }
              }
              if(min != 10000) {
                $minInput.val(min);
              }
            });
          }
          $(window).on('load', function() {
            $element.jRange('setValue', '10000,140000');
            $('fieldset[data-drupal-selector="edit-annual-salary-wrapper"] input[name^="annual_salary"]').val('');
          });
        });

      }
    }
  };
})(jQuery, Drupal, once);
