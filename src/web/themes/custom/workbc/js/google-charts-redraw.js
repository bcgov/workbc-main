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
      dataAttributes['options'].width = width;
    }

    Drupal.googleCharts.drawChart(chartId, dataAttributes['visualization'], dataAttributes['data'], dataAttributes['options'])();
  }

  Drupal.behaviors.redrawGoogleChart = {
    attach: function (context, settings) {
      // Adjust the legend of the donut charts to bottom if we're on mobile.
      if (settings.isMobile) {
        $('.charts-google', context).each(function () {
          const contents = new Drupal.Charts.Contents();
          const chartId = this.id;
          const dataAttributes = contents.getData(chartId);
          if (dataAttributes['visualization'] == "DonutChart") {
            dataAttributes['options'].width = 300;
            dataAttributes['options'].chartArea = {left: 0, right: 0};
            dataAttributes['options'].legend.position = 'bottom';
            Drupal.Charts.Contents.update(chartId, dataAttributes);
            google.charts.setOnLoadCallback(Drupal.googleCharts.drawChart(chartId, dataAttributes['visualization'], dataAttributes['data'], dataAttributes['options']));
          }
        });
      }

      $('.charts-google', context).on('drupalChartsConfigsInitialization', function(e) {
        if (settings.isMobile) {
          const dataAttributes = e.originalEvent.detail;
          if (dataAttributes['visualization'] == 'DonutChart') {
            dataAttributes['options'].width = 300;
            dataAttributes['options'].chartArea = {left: 0, right: 0};
            dataAttributes['options'].legend.position = 'bottom';
          }
        }
      });

      // Force-redraw the chart when its tab is opened.
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
        if (Drupal.Charts && Drupal.googleCharts) {
          Drupal.googleCharts.waitForFinalEvent(function () {
            $('.charts-google').each(function () {
              if (this.dataset.hasOwnProperty('chart')) {
                redrawGoogleChart(this);
              }
            });
          }, 200, 'google-charts-redraw');
        }
      });
    },
  };

})(jQuery, Drupal, once);
