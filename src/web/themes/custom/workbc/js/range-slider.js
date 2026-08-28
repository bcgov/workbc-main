
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
        if ($('.salary-range-search details').width()) {
          const parentWidth = $('.salary-range-search details').width() - WIDTH_DELTA;
          $('.slider-container').remove();
          $('#salary').remove();
          $('fieldset[data-drupal-selector="edit-salary-wrapper"] .fieldset-wrapper').prepend('<div id="salary"></div>');
          setTimeout(resizeSlider, RESIZE_TIMEOUT);
        }
      });

      $(document).on('keydown', function(event) {
        if ($(event.target).is('.pointer')) {
          const $slider = $('#salary');
          const value = $slider.jRange('getValue').split(',').map(v => Number(v));
          const pointer = $(event.target).is('.low') ? 0 : 1;
          if (event.key == "ArrowLeft") {
            value[pointer] = Math.max(0 == pointer ? salaryMin : value[0], value[pointer] - SALARY_STEP);
          }
          else if (event.key == "ArrowRight") {
            value[pointer] = Math.min(1 == pointer ? salaryMax : value[1], value[pointer] + SALARY_STEP);
          }
          else if (event.key == "PageDown") {
            value[pointer] = Math.max(0 == pointer ? salaryMin : value[0], value[pointer] - SALARY_STEP*10);
          }
          else if (event.key == "PageUp") {
            value[pointer] = Math.min(1 == pointer ? salaryMax : value[1], value[pointer] + SALARY_STEP*10);
          }
          else if (event.key == "Home") {
            value[0] = salaryMin;
          }
          else if (event.key == "End") {
            value[1] = salaryMax;
          }
          else return true;
          $slider.jRange('setValue', `${value[0]},${value[1]}`);
          $('.pointer.low', $slider).attr('aria-valuenow', value[0]);
          $('.pointer.high', $slider).attr('aria-valuenow', value[1]);
          return false;
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
          $('.pointer.low', $slider).attr('aria-valuenow', salaryMin);
          $('.pointer.high', $slider).attr('aria-valuenow', salaryMax);
        }

        // Run the ajaxComplete logic ONCE here instead of in initializeRangeSlider
        (function runAjaxCompleteLogicOnce() {
          const $slider = $('#salary');
          const $minInput = $('fieldset[data-drupal-selector="edit-salary-wrapper"] input[name="salary[min]"]');
          const $maxInput = $('fieldset[data-drupal-selector="edit-salary-wrapper"] input[name="salary[max]"]');
          const min = $minInput.val() || salaryMin;
          const max = $maxInput.val() || salaryMax;
          const $salaryOp = $('.plan-careercareer-trek-videos .view-career-trek-redux .career-videos-filters .salary-range-search select[name="salary_op"]');
          if (min && max) {
            $slider.jRange('setValue', `${min},${max}`);
            $('.pointer.low', $slider).attr('aria-valuenow', min);
            $('.pointer.high', $slider).attr('aria-valuenow', max);
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
            showLabels: false,
            showScale: true,
            isRange: true,
            onstatechange: function (value) {
              const [minValue, maxValue] = value.split(',');
              if (value != `${salaryMin},${salaryMin}`) {
                const $wrapper = $('fieldset[data-drupal-selector="edit-salary-wrapper"]');
                $('input[name="salary[min]"]', $wrapper).val(minValue);
                $('input[name="salary[max]"]', $wrapper).val(maxValue);
                $('.scale span:first ins', $wrapper).text(minValue);
                const val = parseInt(maxValue);
                $('.scale span:last ins', $wrapper).text(val === salaryMax ? `${salaryMax}+` : val);
                $('.pointer', $wrapper)
                  .attr('tabindex', 0)
                  .attr('role', 'slider')
                  .attr('aria-valuemin', salaryMin)
                  .attr('aria-valuemax', salaryMax)
                  .attr('aria-label', 'Salary range');
                $('.pointer.low', $wrapper).attr('aria-valuenow', minValue);
                $('.pointer.high', $wrapper).attr('aria-valuenow', maxValue);
              }
            }
          });
          if (!force) {
            $(document).ajaxComplete(function () {
              updateRangeSlider();
            });
          }

          function updateRangeSlider() {
            const $slider = $('#salary');
            const $minInput = $('fieldset[data-drupal-selector="edit-salary-wrapper"] input[name="salary[min]"]');
            const $maxInput = $('fieldset[data-drupal-selector="edit-salary-wrapper"] input[name="salary[max]"]');
            const min = $minInput.val() || salaryMin;
            const max = $maxInput.val() || salaryMax;
            const $salaryOp = $('.plan-careercareer-trek-videos .view-career-trek-redux .career-videos-filters .salary-range-search select[name="salary_op"]');
            if (min && max) {
              $slider.jRange('setValue', `${min},${max}`);
              $('.pointer.low', $slider).attr('aria-valuenow', min);
              $('.pointer.high', $slider).attr('aria-valuenow', max);
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
          }

          $(window).on('load', function() { updateRangeSlider(); });
        });
      }
    }
  };
})(jQuery, Drupal, once);
