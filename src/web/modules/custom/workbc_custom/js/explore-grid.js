let currentOpen = null;

(function ($, Drupal, once) {
  Drupal.behaviors.feedback = {
    attach: function (context, settings) {
      $(once('explore', document.body)).each(function() {
        console.log("explore-grid.js");
          
        let tiles = document.querySelectorAll(".grid-item");
        console.log(tiles);

        Array.from(tiles, function(tile) {     
          tile.addEventListener("click", function() {
            console.log(this.classList[1]);
            let catId = $(this).data('category-id');
            if (currentOpen !== null && currentOpen !== catId) {
              let cat = document.querySelectorAll("#category-id-"+currentOpen);
              $(cat).removeClass('is-selected');
              let areas = document.querySelectorAll("#selector-id-"+currentOpen);
              $(areas).addClass('is-hidden');
            }
            let areas = document.querySelectorAll("#selector-id-"+catId);
            $(this).toggleClass('is-selected');
            $(areas).toggleClass('is-hidden');
            currentOpen = catId;
          });
        });
      });
    }
  }
})(jQuery, Drupal, once);

