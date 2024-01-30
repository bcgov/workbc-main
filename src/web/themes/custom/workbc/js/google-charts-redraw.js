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

      $('.charts-google').each(function () {
        const contents = new Drupal.Charts.Contents();
        const chartId = this.id;
        const dataAttributes = contents.getData(chartId);

        if (dataAttributes['visualization'] == "DonutChart") {
          if (window.matchMedia("(max-width: 767px)").matches) {
            // The viewport is less than 768 pixels wide
            dataAttributes['options'].width = 300;
            dataAttributes['options'].chartArea = {left: 0, right: 0};
            dataAttributes['options'].legend.position = 'bottom';
            
            console.log(dataAttributes['options']);
            if (Drupal.googleCharts.charts.hasOwnProperty(chartId)) {
              Drupal.googleCharts.charts[chartId].clearChart();
            }
            Drupal.googleCharts.drawChart(chartId, dataAttributes['visualization'], dataAttributes['data'], dataAttributes['options'])();
          }       
        }
      });

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
