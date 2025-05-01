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

    // Redraw the chart.
    Drupal.googleCharts.drawChart(chartId, dataAttributes['visualization'], dataAttributes['data'], dataAttributes['options'])();

    // Set up interactivity.
    interactGoogleChart(element);
  }

  function interactGoogleChart(element) {
    const chartId = element.id;
    google.charts.setOnLoadCallback(_ => {
      google.visualization.events.addListener(Drupal.googleCharts.charts[chartId], 'select', _ => {
        const contents = new Drupal.Charts.Contents();
        const dataAttributes = contents.getData(chartId);
        if ('links' in dataAttributes.options) {
          const selection = Drupal.googleCharts.charts[chartId].getSelection();
          if (selection.length > 0 && selection[0].row !== null && selection[0].row in dataAttributes.options.links) {
            window.location = dataAttributes.options.links[selection[0].row];
          }
        }
      });
    });
  }

  Drupal.behaviors.redrawGoogleChart = {
    attach: function (context, settings) {
      $('.nav-link', context).on('shown.bs.tab', function (e) {
        if (Drupal.Charts && Drupal.googleCharts && google.visualization) {
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

      once('click-google-charts-item', '.charts-google').forEach(function (element) {
        if (element.dataset.hasOwnProperty('chart')) {
          interactGoogleChart(element);
        }
      });
    },
  };

})(jQuery, Drupal, once);
