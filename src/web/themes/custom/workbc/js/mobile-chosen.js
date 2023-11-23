let allSelected = true;

(function ($, Drupal, once) {
	Drupal.behaviors.mobilechosen = {
    attach: function (context, drupalSettings) {

      once('mobilechosen', '#edit-occupational-interest', context).forEach(function() {
        if (drupalSettings.isMobile) {
          var select = document.getElementById('edit-occupational-interest'); 
          select.options[0].selected = true;
        }

        $('#edit-occupational-interest').on('change' , function() {
          if (drupalSettings.isMobile) {         
            var select = document.getElementById('edit-occupational-interest');
            var options = select && select.options;

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
                options[0].selected = true;
              }
              else if (select.selectedIndex == -1) {
                allSelected = true;
                options[0].selected = true;                
              }
            }
          }
        });

      });
    }
  }
})(jQuery, Drupal, once);
