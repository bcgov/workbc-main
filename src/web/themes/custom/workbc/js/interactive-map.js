let regions = ['british_columbia', 'cariboo', 'kootenay', 'mainland_southwest', 'north_coast_nechako', 'northeast','thompson_okanagan', 'vancouver_island_coast'];
let currentRegion = {};


(function ($, Drupal, once) {
	Drupal.behaviors.interactivemap = {
    attach: function (context, settings) {

      once('interactivemap', '.workbc-interactive-map-container', context).forEach(function() {
        $('.map-region-label').each(function() {
          let mapNo = $(this).data('interactive-map-no');
          currentRegion[mapNo] = 0;
        });

        $('.map-region-label').on('mouseenter' , function() {
          let mapNo = $(this).data('interactive-map-no');
          let regionNo = $(this).data('interactive-map-region-no');
          $(".workbc-interactive-map-"+mapNo).find(".bc-map-region-"+regions[regionNo]).addClass("active");
        });

        $('.map-region-label').on('mouseleave' , function() {
          let mapNo = $(this).data('interactive-map-no');
          let regionNo = $(this).data('interactive-map-region-no');

          if (regionNo != currentRegion[mapNo]) {
            $(".workbc-interactive-map-"+mapNo).find(".bc-map-region-"+regions[regionNo]).removeClass("active");
          }
        });

        $(document).on('click', '.bc-map-region', function() {         
          let mapType = $(this).closest('.workbc-interactive-map').data('interactive-map-type');
          let mapNo = $(this).closest('.workbc-interactive-map').data('interactive-map-no');
          let regionNo = $(this).data('interactive-map-region-no');

          if (currentRegion[mapNo] !== 0) {
            $(".workbc-interactive-map-"+mapNo).find(".bc-map-region-"+regions[currentRegion[mapNo]]).removeClass("active");
            var element2 = context.querySelector(".workbc-interactive-map-" + mapNo + " .interactive-map-row-"+regions[currentRegion[mapNo]]);
            if (element2 != null) {
              element2.classList.remove("interactive-map-row-hilite");
            }
          }          
          $(this).addClass("active");
          currentRegion[mapNo] = regionNo;

          if (currentRegion[mapNo] !== 0) {
            var element = context.querySelector(".workbc-interactive-map-" + mapNo + " .interactive-map-row-"+regions[currentRegion[mapNo]]);
            if (element != null) {            
              element.classList.add("interactive-map-row-hilite");
              element.scrollIntoView({ block: "center" });
            }   
          }

          if (mapType == "link") {
            let mapId = $(this).attr('id');
            let regionLink = $('.map-region-label-' + mapId).data('interactive-map-region-link');
            window.open(regionLink, "_self");
          }
        });        


        $('.map-region-label').on('click' , function() {
          let mapType = $(this).data('interactive-map-type');
          let mapNo = $(this).data('interactive-map-no');
          let regionNo = $(this).data('interactive-map-region-no');

          if (currentRegion[mapNo] != regionNo && currentRegion[mapNo] != 0) {
            $(".workbc-interactive-map-"+mapNo).find(".bc-map-region-"+regions[currentRegion[mapNo]]).removeClass("active");
            var element2 = context.querySelector(".workbc-interactive-map-" + mapNo + " .interactive-map-row-"+regions[currentRegion[mapNo]]);
            if (element2 != null) {
              element2.classList.remove("interactive-map-row-hilite");
            }
            $(this).addClass("active");
            currentRegion[mapNo] = regionNo;
          }
          else {
            currentRegion[mapNo] = regionNo;
          }
          if (currentRegion[mapNo] != 0) {
            var element = context.querySelector(".workbc-interactive-map-" + mapNo + " .interactive-map-row-"+regions[currentRegion[mapNo]]);
            if (element != null) {            
              element.classList.add("interactive-map-row-hilite");
              element.scrollIntoView({ block: "center" });
            }
          }

          if (mapType == "link") {
            let regionLink = $(this).data('interactive-map-region-link');
            window.open(regionLink, "_self");
          }
        });
      });
    }
  }
})(jQuery, Drupal, once);
