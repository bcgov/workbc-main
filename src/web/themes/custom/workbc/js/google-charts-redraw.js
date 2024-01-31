(function ($, Drupal, once) {
  ("use strict");

  function redrawGoogleChart(element, settings) {
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

    // Adjust the legend of the donut charts to bottom if we're on mobile.
    adjustDonutChartLegend(dataAttributes, settings);

    Drupal.googleCharts.drawChart(chartId, dataAttributes['visualization'], dataAttributes['data'], dataAttributes['options'])();
  }

  function adjustDonutChartLegend(dataAttributes, settings) {
    if (settings.isMobile) {
      if (dataAttributes['visualization'] == 'DonutChart') {
        dataAttributes['options'].width = 300;
        dataAttributes['options'].chartArea = {left: 0, right: 0};
        dataAttributes['options'].legend.position = 'bottom';
      }
    }
  }

  Drupal.behaviors.redrawGoogleChart = {
    attach: function (context, settings) {
      // Adjust the legend of the donut charts to bottom if we're on mobile.
      if (settings.isMobile) {
        $('.charts-google', context).each(function () {
          const contents = new Drupal.Charts.Contents();
          const chartId = this.id;
          const dataAttributes = contents.getData(chartId);
          if (dataAttributes['visualization'] == 'DonutChart') {
            dataAttributes['options'].width = 300;
            dataAttributes['options'].chartArea = {left: 0, right: 0};
            dataAttributes['options'].legend.position = 'bottom';
            Drupal.Charts.Contents.update(chartId, dataAttributes);
            google.charts.setOnLoadCallback(Drupal.googleCharts.drawChart(chartId, dataAttributes['visualization'], dataAttributes['data'], dataAttributes['options']));
          }
        });
      }

      // Respond to chart initialization by adjusting the legend if needed.
      $('.charts-google', context).on('drupalChartsConfigsInitialization', function(e) {
        const dataAttributes = e.originalEvent.detail;
        adjustDonutChartLegend(dataAttributes, settings);
      });

      // Force-redraw the chart when its tab is opened.
      $('.nav-link', context).on('shown.bs.tab', function (e) {
        if (Drupal.Charts && Drupal.googleCharts) {
          $('.charts-google', $(e.target).attr('data-bs-target')).each(function () {
            if (this.dataset.hasOwnProperty('chart')) {
              redrawGoogleChart(this, settings);
            }
          });
        }
      });

      window.addEventListener('resize', function () {
        if (Drupal.Charts && Drupal.googleCharts) {
          Drupal.googleCharts.waitForFinalEvent(function () {
            $('.charts-google').each(function () {
              if (this.dataset.hasOwnProperty('chart')) {
                redrawGoogleChart(this, settings);
              }
            });
          }, 200, 'google-charts-redraw');
        }
      });
    },
  };

})(jQuery, Drupal, once);
