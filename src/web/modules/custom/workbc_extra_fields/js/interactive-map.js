let regions = ['british_columbia', 'cariboo', 'kootenay', 'mainland_southwest', 'north_coast_nechako', 'northeast','thompson_okanagan', 'vancouver_island_coast'];
let currentRegion = [0,0,0,0,0];  // 5 maps


(function ($, Drupal, once) {
	Drupal.behaviors.interactivemap = {
    attach: function (context, settings) {

      once('interactivemap', '.workbc-interactive-map-container', context).forEach(function() {
        $('.map-hot-spot').on('mouseenter' , function() {
          let mapNo = $(this).data('interactive-map-no');
          let regionNo = $(this).data('interactive-map-region-no');

          var element = context.querySelector("#workbc-interactive-map-" + mapNo + " .interactive-map-" + regions[regionNo]);
          element.style.visibility = "visible";
          element.style.display = "flex";          
        });

        $('.map-hot-spot').on('mouseleave' , function() {
          let mapNo = $(this).data('interactive-map-no');
          let regionNo = $(this).data('interactive-map-region-no');          

          if (regionNo != currentRegion[mapNo]) {
            var element = context.querySelector("#workbc-interactive-map-" + mapNo + " .interactive-map-" + regions[regionNo]);
            element.style.visibility = "hidden";
            element.style.display = "none";
          }
        });

        $('.map-hot-spot').on('click' , function() {
          let mapNo = $(this).data('interactive-map-no');
          let regionNo = $(this).data('interactive-map-region-no');     
          
          if (currentRegion[mapNo] != regionNo && currentRegion[mapNo] != 0) {
            var element = context.querySelector("#workbc-interactive-map-" + mapNo + " .interactive-map-" + regions[currentRegion[mapNo]]);
            element.style.visibility = "hidden";
            element.style.display = "none";
            var element2 = context.querySelector("#workbc-interactive-map-" + mapNo + " .interactive-map-row-"+regions[currentRegion[mapNo]]);
            element2.classList.remove("interactive-map-row-hilite");
            currentRegion[mapNo] = regionNo;
          } 
          // else if (currentRegion[mapNo] == regionNo) {
          //   var element2 = context.querySelector("#workbc-interactive-map-" + mapNo + " .interactive-map-row-"+regions[currentRegion[mapNo]]);
          //   element2.classList.remove("interactive-map-row-hilite");    
          //   currentRegion[mapNo] =  0;
          // }
          else {
            currentRegion[mapNo] = regionNo;
          }
          if (currentRegion[mapNo] != 0) {
            var element = context.querySelector("#workbc-interactive-map-" + mapNo + " .interactive-map-row-"+regions[currentRegion[mapNo]]);
            element.classList.add("interactive-map-row-hilite");
          }
        });
      });
    }
  }
})(jQuery, Drupal, once);





// function mapHoverOn(map, region) {

//   var element = document.querySelector("#workbc-interactive-map-" + map + " .interactive-map-" + regions[region]);
//   element.style.visibility = "visible";
//   element.style.display = "flex";
// }


// function mapHoverOff(map, region) {
 
//   if (region != currentRegion[map]) {
//     var element = document.querySelector("#workbc-interactive-map-" + map + " .interactive-map-" + regions[region]);
//     element.style.visibility = "hidden";
//     element.style.display = "none";
//   }
// }

// function mapClick(map, region) {

//   if (currentRegion[map] != region && currentRegion[map] != 0) {
//     var element = document.querySelector("#workbc-interactive-map-" + map + " .interactive-map-" + regions[currentRegion[map]]);
//     element.style.visibility = "hidden";
//     element.style.display = "none";
//     var element2 = document.querySelector("#workbc-interactive-map-" + map + " .interactive-map-row-"+regions[currentRegion[map]]);
//     element2.classList.remove("interactive-map-row-hilite");
//   } 
//   currentRegion[map] = region;

//   var element = document.querySelector("#workbc-interactive-map-" + map + " .interactive-map-row-"+regions[currentRegion[map]]);
//   element.classList.add("interactive-map-row-hilite");
// }