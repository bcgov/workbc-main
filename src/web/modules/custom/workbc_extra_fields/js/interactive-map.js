function mapHover(region) {
  let regions = ['', 'cariboo', 'kootenay', 'mainland', 'northcoast', 'northeast','thompson', 'vancouver'];
  console.log(regions[region]);

  document.getElementById("interactive-map-northeast").style.visiblity = "visible";
  document.getElementById("interactive-map-northeast").style.display = "flex";
}

