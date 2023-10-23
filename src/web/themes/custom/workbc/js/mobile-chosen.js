let allSelected = true;

(function ($, Drupal, once) {
	Drupal.behaviors.mobilechosen = {
    attach: function (context, settings) {

      once('mobilechosen', '#edit-occupational-interest', context).forEach(function() {

        var select = document.getElementById('edit-occupational-interest');
        var option = document.createElement('option');
        option.value = 0;
        option.innerHTML = "All";
        option.selected = true;
        select.insertBefore(option, select.firstChild);

        oldOptions = select && select.options;


        $('#edit-occupational-interest').on('change' , function() {
          var select = document.getElementById('edit-occupational-interest');
          var options = select && select.options;
;

          if (allSelected) {
            for (var i=0, iLen=options.length; i<iLen; i++) {
              opt = options[i];             
              if (opt.selected && i > 0) {
                options[0].selected = false;
                allSelected = false;                
              }
            }
          }
          else {
            if (select.selectedIndex == 0) {
              for (var i=1, iLen=options.length; i<iLen; i++) {
                opt = options[i];             
                if (opt.selected && i > 0) {
                  options[i].selected = false;           
                }
              }              
              allSelected = true;
            }
          }

        });



      });
    }
  }
})(jQuery, Drupal, once);
