
(function ($, Drupal, once) {
  "use strict";

  const RESIZE_TIMEOUT = 100;
  const WIDTH_DELTA = 15;
  const SALARY_STEP = 1000;

  Drupal.behaviors.rangeSlider = {
    attach: function (context, settings) {
      // Only initialize ONCE on attach.
      const salaryMin = 10000;
      const salaryMax = 140000;
      once('rangeSlider', '#salary', context).forEach(initializeSlider);

      // Re-initialize the slider on window resize
      $(window).on('resize', function () {
        const $slider = $('#salary');
        if ($('.salary-range-search details').width()) {
          const parentWidth = $('.salary-range-search details').width() - WIDTH_DELTA;
          $('.slider-container').remove();
          $('#salary').remove();
          $('fieldset[data-drupal-selector="edit-salary-wrapper"] .fieldset-wrapper').prepend('<div id="salary"></div>');
          setTimeout(resizeSlider, RESIZE_TIMEOUT);
        }

      });
      $('.responsive-filter-video-btn', context).on('click', function () {
        setTimeout(resizeSlider, RESIZE_TIMEOUT);
      });

      function initializeSlider(element) {
        const $element = $(element);
        if ($('.salary-range-search details').width()) {
          const parentWidth = $('.salary-range-search details').width() - WIDTH_DELTA;
          initializeRangeSlider($element, parentWidth, context);
        }
      }

      // Fix: Do NOT use once() in resizeSlider, just update the slider directly.
      function resizeSlider() {
        const newWidth = $('.salary-range-search details').width() - WIDTH_DELTA;
        const $slider = $('#salary');
        initializeRangeSlider($slider, newWidth, context, true);
        const $minInput = $('fieldset[data-drupal-selector="edit-salary-wrapper"] input[name="salary[min]"]');
        const $maxInput = $('fieldset[data-drupal-selector="edit-salary-wrapper"] input[name="salary[max]"]');
        if (!$minInput.val() && !$maxInput.val()) {
          $slider.jRange('setValue', `${salaryMin},${salaryMax}`);
        }
        // Run the ajaxComplete logic ONCE here instead of in initializeRangeSlider
        (function runAjaxCompleteLogicOnce() {
          const $minInput = $('fieldset[data-drupal-selector="edit-salary-wrapper"] input[name="salary[min]"]');
          const $maxInput = $('fieldset[data-drupal-selector="edit-salary-wrapper"] input[name="salary[max]"]');
          const min = $minInput.val() || salaryMin;
          const max = $maxInput.val() || salaryMax;
          const $salaryOp = $('.plan-careercareer-trek-videos .view-career-trek-redux .career-videos-filters .salary-range-search select[name="salary_op"]');
          if (min && max) {
            $(`#salary`).jRange('setValue', `${min},${max}`);
          }

          $salaryOp.val('between');
          if (max != salaryMax) {
            $maxInput.val(max);
            if (min == salaryMin) {
              $minInput.val(min);
            }
          }
          if (min != salaryMin) {
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
            from: salaryMin,
            to: salaryMax,
            step: SALARY_STEP,
            width: (width > 0 && width !== "") ? width : 300,
            format: function (value) {
              const val = parseInt(value);
              return (val === salaryMax) ? `${salaryMax}+` : val;
            },
            showLabels: true,
            isRange: true,
            onstatechange: function (value) {
              const [minValue, maxValue] = value.split(',');
              if (value != `${salaryMin},${salaryMin}`) {
                $('fieldset[data-drupal-selector="edit-salary-wrapper"] input[name="salary[min]"]').val(minValue);
                $('fieldset[data-drupal-selector="edit-salary-wrapper"] input[name="salary[max]"]').val(maxValue);
              }
            }
          });
          if(!force) {
            $(document).ajaxComplete(function () {
              const $minInput = $('fieldset[data-drupal-selector="edit-salary-wrapper"] input[name="salary[min]"]');
              const $maxInput = $('fieldset[data-drupal-selector="edit-salary-wrapper"] input[name="salary[max]"]');
              const min = $minInput.val() || salaryMin;
              const max = $maxInput.val() || salaryMax;
              const $salaryOp = $('.plan-careercareer-trek-videos .view-career-trek-redux .career-videos-filters .salary-range-search select[name="salary_op"]');
              if (min && max) {
                $(`#salary`).jRange('setValue', `${min},${max}`);
              }

              $salaryOp.val('between');
              if (max != salaryMax) {
                $maxInput.val(max);
                if (min == salaryMin) {
                  $minInput.val(min);
                }
              }
              if (min != salaryMin) {
                $minInput.val(min);
              }
            });
          }
        });
      }
    }
  };
})(jQuery, Drupal, once);
