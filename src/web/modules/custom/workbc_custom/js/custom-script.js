(function ($, Drupal) {
  Drupal.behaviors.customscript = {
    attach: function (context, settings) {

      var currentUrl = window.location.href.split('?')[0];

      //education change
      $('#education-level').change( function() {
        var value = $(this).val();

        var redirectUrl = currentUrl+'?education='+value;

        if($('#region').val()){
          var region = $('#region').val();
          redirectUrl = redirectUrl+'&region='+region;
        }

        if($('#occupational-interest').val()){
          var interest = $('#occupational-interest').val();
          redirectUrl = redirectUrl+'&interest='+interest;
        }

        window.location.href = redirectUrl;
      });

      //region change
      $('#region').change( function() {
        var value = $(this).val();

        var redirectUrl = currentUrl+'?region='+value;

        if($('#education-level').val()){
          var education = $('#education-level').val();
          redirectUrl = redirectUrl+'&education='+education;
        }

        if($('#occupational-interest').val()){
          var interest = $('#occupational-interest').val();
          redirectUrl = redirectUrl+'&interest='+interest;
        }

        window.location.href = redirectUrl;
      });

      //interest change
      $('#occupational-interest').change( function() {
        var value = $(this).val();

        var redirectUrl = currentUrl+'?interest='+value;

        if($('#education-level').val()){
          var education = $('#education-level').val();
          redirectUrl = redirectUrl+'&education='+education;
        }

        if($('#region').val()){
          var region = $('#region').val();
          redirectUrl = redirectUrl+'&region='+region;
        }

        window.location.href = redirectUrl;
      });
 
    }
  }
})(jQuery, Drupal);