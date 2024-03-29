let regions = ['british_columbia', 'cariboo', 'kootenay', 'mainland_southwest', 'north_coast_nechako', 'northeast','thompson_okanagan', 'vancouver_island_coast'];
let currentRegion = {};

(function ($, Drupal, once) {
	Drupal.behaviors.interactivemap = {
    attach: function (context, settings) {

      once('interactivemap', '.workbc-interactive-map-container', context).forEach(function() {
        $('.map-hot-spot').each(function() {
          let mapNo = $(this).data('interactive-map-no');
          currentRegion[mapNo] = 0;
        });

        $('.map-hot-spot').on('mouseenter' , function() {
          let mapNo = $(this).data('interactive-map-no');
          let regionNo = $(this).data('interactive-map-region-no');

          var element = context.querySelector(".workbc-interactive-map-" + mapNo + " .interactive-map-" + regions[regionNo]);
          element.style.visibility = "visible";
          element.style.display = "flex";
        });

        $('.map-hot-spot').on('mouseleave' , function() {
          let mapNo = $(this).data('interactive-map-no');
          let regionNo = $(this).data('interactive-map-region-no');

          if (regionNo != currentRegion[mapNo]) {
            var element = context.querySelector(".workbc-interactive-map-" + mapNo + " .interactive-map-" + regions[regionNo]);
            element.style.visibility = "hidden";
            element.style.display = "none";
          }
        });

        $('.map-hot-spot').on('click' , function() {
          let mapType = $(this).data('interactive-map-type');
          let mapNo = $(this).data('interactive-map-no');
          let regionNo = $(this).data('interactive-map-region-no');
          let regionLink = $(this).data('interactive-map-region-link');

          if (currentRegion[mapNo] != regionNo && currentRegion[mapNo] != 0) {
            var element = context.querySelector(".workbc-interactive-map-" + mapNo + " .interactive-map-" + regions[currentRegion[mapNo]]);
            element.style.visibility = "hidden";
            element.style.display = "none";
            var element2 = context.querySelector(".workbc-interactive-map-" + mapNo + " .interactive-map-row-"+regions[currentRegion[mapNo]]);
            if (element2 != null) {
              element2.classList.remove("interactive-map-row-hilite");
            }
            currentRegion[mapNo] = regionNo;
          }
          // else if (currentRegion[mapNo] == regionNo) {
          //   var element2 = context.querySelector(".workbc-interactive-map-" + mapNo + " .interactive-map-row-"+regions[currentRegion[mapNo]]);
          //   element2.classList.remove("interactive-map-row-hilite");
          //   currentRegion[mapNo] =  0;
          // }
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
            window.open(regionLink, "_self");
          }
        });
      });
    }
  }
})(jQuery, Drupal, once);
