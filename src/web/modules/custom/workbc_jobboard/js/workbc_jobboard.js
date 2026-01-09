(function (Drupal, $, once) {
	Drupal.behaviors.jobboard = {
    attach: function (context, settings){
      once('jobboard', '.block-workbc-jobboard', context).forEach(function() {
        $(once('jobboard', 'a', context)).filter(function() {
          return this.hostname && this.hostname !== location.hostname;
        }).click(function(e) {
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
        $(once("jobboard", ".region-map-select select", context)).on("change", function(){
          if($(this).val() != ""){
            window.location.href=$(this).val();
          }
        });
      });

      once('jobboard', '.job-search', context).forEach(function() {
        $('#find-job .job-search__title').each(function() {
          const that = this;
          $.ajax({
            url: settings.jobboard.totalJobs,
            data: {
              't': Date.now(),
            },
            method: 'GET',
            headers: {
              'Accept': '*/*',
              'Content-Type': 'application/json',
            },
            success: function (data) {
              const totalJobs = parseInt(data).toLocaleString('en-CA');
              const html = $(that).html().replace(/[\d,]+/, totalJobs);
              $(that).html(html);
            },
            error: function () {
              console.error(`[Job Board] Error getting total jobs`);
            }
          });
        });
      });

      once('jobboard', '.workbc-jobboard-save-profile', context).forEach(function() {
        const token = readCookie('currentUser.token');
        if (token) {
          $.ajax({
            url: settings.jobboard.status,
            method: 'GET',
            headers: {
              'Accept': '*/*',
              'Content-Type': 'application/json',
              'Authorization': `Bearer ${token}`,
            },
            success: function (data) {
              const saved = !!data;
              if (saved) {
                $('.workbc-jobboard-save-profile input.form-submit')
                  .val('Saved')
                  .css('visibility', 'visible')
                  .attr('disabled', true);
              }
              else {
                $('.workbc-jobboard-save-profile input.form-submit')
                  .val('Save this profile')
                  .css('visibility', 'visible')
                  .attr('disabled', false)
                  .on('click', function() {
                    $.ajax({
                      url: settings.jobboard.save,
                      method: 'POST',
                      headers: {
                        'Accept': '*/*',
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${token}`,
                      },
                      success: function(data) {
                        const messages = new Drupal.Message();
                        messages.add('Profile successfully added.');
                        $('.workbc-jobboard-save-profile input.form-submit')
                          .val('Saved')
                          .css('visibility', 'visible')
                          .attr('disabled', true);
                      },
                      error: function() {
                        console.error(`[Job Board] Error saving profile`);
                      }
                    });
                  })
              }
            },
            error: function () {
              console.error(`[Job Board] Error getting profile status`);
            }
          });
        }
        else {
          $('.workbc-jobboard-save-profile input.form-submit')
            .val('Save this profile')
            .css('visibility', 'visible')
            .attr('disabled', false)
            .on('click', function() {
              if (window.localStorage) {
                window.localStorage.setItem(settings.jobboard.storageKey, settings.jobboard.profileId);
                if (settings.jobboard.urlKey) {
                  window.localStorage.setItem(settings.jobboard.urlKey, window.location.href);
                }
              }
              window.location.href = '/account#/login';
            });
        }
      });

      const navLogout = function() {
        localStorage.removeItem('currentUser');
        document.cookie = 'currentUser.username=; expires=Thu, 01 Jan 1970 00:00:00 UTC; Path=/;';
        document.cookie = 'currentUser.email=; expires=Thu, 01 Jan 1970 00:00:00 UTC; Path=/;';
        document.cookie = 'currentUser.firstName=; expires=Thu, 01 Jan 1970 00:00:00 UTC; Path=/;';
        document.cookie = 'currentUser.lastName=; expires=Thu, 01 Jan 1970 00:00:00 UTC; Path=/;';
        document.cookie = 'currentUser.id=; expires=Thu, 01 Jan 1970 00:00:00 UTC; Path=/;';
        document.cookie = 'currentUser.token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; Path=/;';
        if (location.pathname === '/account') location.reload(true); else return true;
      }

      const navUserMenu = function() {
        const currentUser = readCookie('currentUser.username');

        // Desktop menu
        const $dropdown = $('.nav-user .dropdown-toggle');
        $dropdown.attr('data-bs-toggle', 'dropdown');
        $('#menu-item-unlogged-account').on('click', () => false);
        $('#menu-item-logged-account').on('click', () => false);
        $('#menu-item-logout').on('click', navLogout);
        if (currentUser != '') {
          $('#menu-item-logged-account').parent().show();
          $('#menu-item-unlogged-account').parent().hide();
        }
        else {
          $('#menu-item-logged-account').parent().hide();
          $('#menu-item-unlogged-account').parent().show();
        }

        // Mobile menu
        const $unlogged = $('.menu-item--unlogged-account');
        $('.mm-listitem__text', $unlogged).attr('href', $('.mm-btn', $unlogged).attr('href'));
        const $logged = $('.menu-item--logged-account');
        $('.mm-listitem__text', $logged).attr('href', $('.mm-btn', $logged).attr('href'));
        $('.menu-item--logged-logout a').on('click', navLogout);
        if (currentUser != '') {
          $logged.show();
          $unlogged.hide();
        }
        else {
          $logged.hide();
          $unlogged.show();
        }
      };

      once('jobboard', 'html', context).forEach(function() {
        window.addEventListener('load', navUserMenu);
        window.addEventListener('hashchange', navUserMenu);
        window.addEventListener('jobboardlogin', navUserMenu);
        window.addEventListener('dialog:aftercreate', navUserMenu);
      });
    }
  }
})(Drupal, jQuery, once);

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
        document.cookie = `${split[0]}.${prop}=${entry[prop]}; Path=/;`;
      }

      return entry[split[1]];
    }
    catch {
      // Do nothing, continue with the cookie access.
    }
  }

  var d = [],
  e = document.cookie.split(";");
  cookieName = RegExp("^\\s*" + cookieName + "=\\s*(.*?)\\s*$");
  for (var b=0; b<e.length; b++){
    var f = e[b].match(cookieName);
    if (f) d.push(f[1]);
  }
  return d[0] || '';
}
