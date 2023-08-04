let regions = ['british_columbia', 'cariboo', 'kootenay', 'mainland_southwest', 'north_coast_nechako', 'northeast','thompson_okanagan', 'vancouver_island_coast'];
let currentRegion = [0,0,0,0,0];  // 5 maps


function mapHoverOn(map, region) {

  var element = document.querySelector("#workbc-interactive-map-" + map + " .interactive-map-" + regions[region]);
  element.style.visibility = "visible";
  element.style.display = "flex";
}


function mapHoverOff(map, region) {
 
  if (region != currentRegion[map]) {
    var element = document.querySelector("#workbc-interactive-map-" + map + " .interactive-map-" + regions[region]);
    element.style.visibility = "hidden";
    element.style.display = "none";
  }
}

function mapClick(map, region) {

  if (currentRegion[map] != region && currentRegion[map] != 0) {
    var element = document.querySelector("#workbc-interactive-map-" + map + " .interactive-map-" + regions[currentRegion[map]]);
    element.style.visibility = "hidden";
    element.style.display = "none";
    var element2 = document.querySelector("#workbc-interactive-map-" + map + " .interactive-map-row-"+regions[currentRegion[map]]);
    element2.classList.remove("interactive-map-row-hilite");
  } 
  currentRegion[map] = region;

  var element = document.querySelector("#workbc-interactive-map-" + map + " .interactive-map-row-"+regions[currentRegion[map]]);
  element.classList.add("interactive-map-row-hilite");
}