let regions = ['', 'cariboo', 'kootenay', 'mainland_southwest', 'north_coast_nechako', 'northeast','thompson_okanagan', 'vancouver_island_coast'];
let currentRegion = 0;
let pastRegion = 0;

function mapHoverOn(map, region) {
console.log(map);
console.log(region);

  var element = document.querySelector("#workbc-interactive-map-" + map + " #interactive-map-" + regions[region]);
  element.style.visibility = "visible";
  element.style.display = "flex";
}


function mapHoverOff(map, region) {
  
  // workbc_interactive-map-

  if (region != currentRegion) {
    var element = document.querySelector("#workbc-interactive-map-" + map + " #interactive-map-" + regions[region]);
    element.style.visibility = "hidden";
    element.style.display = "none";
  }
}

function mapClick(map, region) {
  if (currentRegion != region && currentRegion != 0) {
    var element = document.querySelector("#workbc-interactive-map-" + map + " interactive-map-" + regions[currentRegion]);
    element.style.visibility = "hidden";
    element.style.display = "none";
    var element = document.querySelector("#workbc-interactive-map-" + map + " interactive-map-row-"+regions[currentRegion]);
    element.classList.remove("interactive-map-row-hilite");
  } 
  currentRegion = region;

  var element = document.querySelector("#workbc-interactive-map-" + map + " interactive-map-row-"+regions[currentRegion]);
  element.classList.add("interactive-map-row-hilite");
}