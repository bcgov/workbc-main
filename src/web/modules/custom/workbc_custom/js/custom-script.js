(function ($, Drupal) {
  Drupal.behaviors.customscript = {
    attach: function (context, settings) {

      //education change
      $('#education-level').change( function() {
        window.location.href = redirectUrl();
      });

      //region change
      $('#region').change( function() {
        window.location.href = redirectUrl();
      });

      //interest change
      $('#occupational-interest').change( function() {
        window.location.href = redirectUrl();
      });

      //wage change
      $('#wage').change( function() {
        window.location.href = redirectUrl();
      });

      var redirectUrl = function (){

        var redirectUrl = window.location.href.split('?')[0];
        var parameters = [];

        if($('#education-level').val()){
          var education = $('#education-level').val();
          parameters.push('education='+education);
        }

        if($('#region').val()){
          var region = $('#region').val();
          parameters.push('region='+region);
        }

        if($('#occupational-interest').val()){
          var interest = $('#occupational-interest').val();
          parameters.push('interest='+interest);
        }

        if($('#wage').val()){
          var wage = $('#wage').val();
          parameters.push('wage='+wage);
        }

        redirectUrl = redirectUrl + '?' + parameters.join('&');

        return redirectUrl;
      }

    }
  }
})(jQuery, Drupal);