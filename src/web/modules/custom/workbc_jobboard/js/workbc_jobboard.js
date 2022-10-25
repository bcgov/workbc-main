(function ($, Drupal) {
	Drupal.behaviors.jobboard = {
    attach: function (context, settings){
      $('.block-workbc-jobboard', context).once('jobboard').ready(function(){
        $('a').filter(function() {
          return this.hostname && this.hostname !== location.hostname;
        }).once('jobboard').click(function(e) {
          var url = $(this).attr('href');
          let domain = (new URL(url));
          domain = domain.hostname.replace('www.',''); 
          domain = domain.split(".");
          domain.pop();
          domain = domain.join(' ');
          if(!confirm("This job posting is on another organization’s job board. By continuing, you will be directed to the original posting at "+domain)){
            e.preventDefault();
          };
        });
        
      });
      $(window, context).once('jobboard').on('hashchange load jobboardlogin', function (e) {
        var currentUser = readCookie('currentUser.username');
        var CheckLoginLinkExists = $("nav.nav-user .nav-items li.new-login-link");
        var CheckLogoutLinkExists = $("nav.nav-user .nav-items li.new-logout-link");
        
          if(currentUser != ''){
            if(CheckLoginLinkExists.length < 1){
              CheckLogoutLinkExists.remove();
              $("nav.nav-user .nav-items").append("<li class='nav-item new-login-link'> <a  href='/account#/dashboard' class='nav-link'>My Account</a><li class='nav-item new-login-link'> <a  href='/account/#/logout' class='nav-link' onclick=\"document.cookie = 'currentUser.username=; expires=Thu, 01 Jan 1970 00:00:00 UTC; Path=/'; document.cookie = 'currentUser.email=; expires=Thu, 01 Jan 1970 00:00:00 UTC; Path=/';document.cookie = 'currentUser.firstname=; expires=Thu, 01 Jan 1970 00:00:00 UTC; Path=/';document.cookie = 'currentUser.lastname=; expires=Thu, 01 Jan 1970 00:00:00 UTC; Path=/';document.cookie = 'currentUser.id=; expires=Thu, 01 Jan 1970 00:00:00 UTC; Path=/';document.cookie = 'currentUser.token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; Path=/';localStorage.removeItem('currentUser'); location.reload();\"> Log out </a>");
            }
          }else{
            if(CheckLogoutLinkExists.length < 1){
              CheckLoginLinkExists.remove();
              $("nav.nav-user .nav-items").append("<li class='nav-item new-logout-link'> <a href='/account#/login' class='nav-link' > Log in </a><li class='nav-item new-logout-link'> <a href='/account#/register' class='nav-link' > Register </a>");
            }
          }
      });
    } 
  }
})(jQuery, Drupal);

jQuery(window).on('hashchange', function (e) {
//  console.log("Location: "+location.hash );
  if(location.hash == '#/saved-career-profiles'){
    setTimeout(function(){
      if (typeof searchCareerProfileLink !== 'undefined') {
        var href = jQuery('app-root .saved-careers-inner .col-lg-6:first .info-box a').attr('href', searchCareerProfileLink);
      }
      if (typeof LabourMarketOutlook !== 'undefined') {
        var href2 = jQuery('app-root .saved-careers-inner .col-lg-6:last .info-box a').attr('href', LabourMarketOutlook);
      }
    }, 500);
  }else if(location.hash == '#/saved-industry-profiles'){
    setTimeout(function(){
      if (typeof ViewIndustryProfiles !== 'undefined') {
        var href = jQuery('app-root .saved-careers-inner .col-lg-6:first .info-box a').attr('href', ViewIndustryProfiles);
      }
      if (typeof ExploreIndustryandSectorOutlooks !== 'undefined') {
        var href2 = jQuery('app-root .saved-careers-inner .col-lg-6:last .info-box a').attr('href', ExploreIndustryandSectorOutlooks);
      }
    }, 500);
  }else if(location.hash == '#/job-alerts/create'){
    setTimeout(function(){
//      console.log("In "+JobSearchTips);
      if (typeof JobSearchTips !== 'undefined') {
        var href3 = jQuery('app-root .account-contain a.find-jobs-btn').attr('href', JobSearchTips);
      }
    }, 500);
  }
});

//console.log("Hash Out: "+window.location.hash);
if (window.location.hash) {
//  console.log("Hash In: "+window.location.hash);
  jQuery(window).trigger('hashchange');
  if(window.location.hash != "#/register"){
    if(history.pushState) {
      history.pushState(null, null, '#/personal-settings');
    }
    else {
        location.hash = '#/personal-settings';
    }
  }
}

function readCookie(cookieName){
  var d=[],
  e=document.cookie.split(";");
  cookieName=RegExp("^\\s*"+cookieName+"=\\s*(.*?)\\s*$");
  for(var b=0;b<e.length;b++){
    var f=e[b].match(cookieName);
    f&&d.push(f[1])
  }
  return d;
}
