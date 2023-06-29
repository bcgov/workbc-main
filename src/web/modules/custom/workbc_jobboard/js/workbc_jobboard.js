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
          if($(this).parents('.job-title').length || $(this).parents('.node--view-mode-jobboard').length ){
            if(!confirm("This job posting is on another organization’s job board. By continuing, you will be directed to the original posting at "+domain)){
              e.preventDefault();
            };
          }
        });
        $(".region-map-select select", context).once('jobboard').on("change", function(){
          if($(this).val() != ""){
            window.location.href=$(this).val();
          }
        });
      });

      $(window, context).once('jobboard').on('hashchange load jobboardlogin', function (e) {
        var currentUser = readCookie('currentUser.username');
        var CheckLoginLinkExists = $("nav.nav-user .nav-items li.new-login-link");
        var CheckLogoutLinkExists = $("nav.nav-user .nav-items li.new-logout-link");

        var CheckLoginLinkMobileExists = $(".mobile-nav__user-nav .nav-items li.new-login-link");
        var CheckLogoutLinkMobileExists = $(".mobile-nav__user-nav .nav-items li.new-logout-link");

          if(currentUser != ''){
            //
            if(CheckLoginLinkExists.length < 1){
              CheckLogoutLinkExists.remove();
              CheckLogoutLinkMobileExists.remove();
              var appendLoginMenusM = `
<li class="nav-item new-login-link">
  <a href="/account#/dashboard" class="nav-link">My Profile</a>
</li>
<li class="nav-item new-login-link">
  <a  href="/account#/personal-settings" class="nav-link">Personal Settings</a>
</li>
<li class="nav-item new-login-link">
  <a href="/account#/logout" class="nav-link" onclick="localStorage.removeItem('currentUser'); document.cookie='currentUser.username=; expires=Thu, 01 Jan 1970 00:00:00 UTC; Path=/;'; document.cookie='currentUser.email=; expires=Thu, 01 Jan 1970 00:00:00 UTC; Path=/;'; document.cookie='currentUser.firstName=; expires=Thu, 01 Jan 1970 00:00:00 UTC; Path=/;'; document.cookie='currentUser.lastName=; expires=Thu, 01 Jan 1970 00:00:00 UTC; Path=/;'; document.cookie='currentUser.id=; expires=Thu, 01 Jan 1970 00:00:00 UTC; Path=/;'; document.cookie='currentUser.token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; Path=/;'; if (location.pathname === '/account') location.reload(true); else return true;">Log out</a>
</li>
`;
              var appendLoginMenusD = `
<li class="nav-item new-login-link dropdown">
  <a  href="javascript:void(0)" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">My Account</a>
  <ul class="dropdown-menu">
    ${appendLoginMenusM}
  </ul>
</li>
`;

              //Desktop menu
              $("nav.nav-user .nav-items").append(appendLoginMenusD);

              //Mobile Menu
              $(".mobile-nav__user-nav .nav-items").append(appendLoginMenusM);
            }
          }else{
            if(CheckLogoutLinkExists.length < 1){
              CheckLoginLinkExists.remove();
              CheckLoginLinkMobileExists.remove();
              var appendLogoutMenus = `
<li class="nav-item new-logout-link">
  <a href="/account#/login" class="nav-link">Log in</a>
</li>
<li class="nav-item new-logout-link">
  <a href="/account#/register" class="nav-link">Register</a>
</li>
`;
              $("nav.nav-user .nav-items").append(appendLogoutMenus);
              $(".mobile-nav__user-nav .nav-items").append(appendLogoutMenus);
            }
          }
      });
      $('.dropdown .dropdown-toggle').click(function(){
         if($(this).parent().hasClass('open')){
             $(this).parent().removeClass('open');
             $(this).next('.dropdown-menu').hide();
         }else {
          $(this).next('.dropdown-menu').show();
          $(this).parent().addClass('open');
         }
      });
    }
  }
})(jQuery, Drupal);


if (window.location.hash) {
  jQuery(window).trigger('hashchange');
}


function readCookie(cookieName){
  if (window.localStorage) {
    const split = cookieName.split('.');
    try {
      const entry = JSON.parse(window.localStorage.getItem(split[0]));

      // Set each cookie.
      for (const prop in entry) {
        document.cookie= `${split[0]}.${prop}=${entry[prop]}; Path=/;`;
      }

      return entry[split[1]];
    }
    catch {
      // Do nothing, continue with the cookie access.
    }
  }

  var d=[],
  e=document.cookie.split(";");
  cookieName=RegExp("^\\s*"+cookieName+"=\\s*(.*?)\\s*$");
  for(var b=0;b<e.length;b++){
    var f=e[b].match(cookieName);
    f&&d.push(f[1])
  }
  return d[0] || '';
}
