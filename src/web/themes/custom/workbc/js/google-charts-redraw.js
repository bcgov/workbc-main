(function ($, Drupal, once) {
  ("use strict");

  function redrawGoogleChart(element) {
    const contents = new Drupal.Charts.Contents();
    const chartId = element.id;
    if (Drupal.googleCharts.charts.hasOwnProperty(chartId)) {
      Drupal.googleCharts.charts[chartId].clearChart();
    }
    const dataAttributes = contents.getData(chartId);

    // Manually fix the chart width to 100%.
    if (!dataAttributes['options'].width) {
      const width = $(element).closest('.card-profile__content').width();
      console.log(width);
      dataAttributes['options'].width = width;
    }

    Drupal.googleCharts.drawChart(chartId, dataAttributes['visualization'], dataAttributes['data'], dataAttributes['options'])();
  }

  Drupal.behaviors.initPopoverBehavior = {
    attach: function (context, settings) {
      $('.nav-link', context).on('shown.bs.tab', function (e) {
        if (Drupal.Charts && Drupal.googleCharts) {
          $('.charts-google', $(e.target).attr('data-bs-target')).each(function () {
            if (this.dataset.hasOwnProperty('chart')) {
              redrawGoogleChart(this);
            }
          });
        }
      });

      window.addEventListener('resize', function () {
        Drupal.googleCharts.waitForFinalEvent(function () {
          $('.charts-google').each(function () {
            if (this.dataset.hasOwnProperty('chart')) {
              redrawGoogleChart(this);
            }
          });
        }, 200, 'google-charts-redraw');
      });
    },
  };

})(jQuery, Drupal, once);
