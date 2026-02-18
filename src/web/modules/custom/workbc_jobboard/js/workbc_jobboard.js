(function (Drupal, $, once) {
	Drupal.behaviors.jobboard = {
    attach: function (context, settings) {
      once('jobboard', 'html', context).forEach(function() {
        window.addEventListener('load', navUserMenu);
        window.addEventListener('hashchange', navUserMenu);
        window.addEventListener('jobboardlogin', navUserMenu);
        window.addEventListener('dialog:aftercreate', navUserMenu);
      });

      once('jobboard', 'body.account', context).forEach(function() {
        window.addEventListener('load', accountPageChanges);
        window.addEventListener('hashchange', accountPageChanges);
        window.addEventListener('jobboardlogin', accountPageChanges);
        window.addEventListener('jobboardlogin', closePanel);
      });

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

        $(once('jobboard', '.region-map-select select', context)).on('change', function(){
          const val = $(this).val();
          if (val) {
            window.location.href = val;
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

      // FIXME: Copied from src/web/modules/contrib/gtranslate/js/dropdown.js
      // because I couldn't figure out how to trigger the loading of the library. It should be triggerable as per:
      // document.querySelectorAll(u_class).forEach(function(e){e.addEventListener('pointerenter',load_tlib)});
      function load_tlib(){if(!window.gt_translate_script){window.gt_translate_script=document.createElement('script');gt_translate_script.src='https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit2';document.body.appendChild(gt_translate_script);}}

      const cookie_lang = document.cookie
        .split("; ")
        .find((row) => row.startsWith("googtrans="))
        ?.split("=")[1];
      let job_lang = cookie_lang === '/en/fr';
      $('.job-lang input', context).prop('checked', job_lang);
      $(once('jobboard', '.job-lang', context)).on('click', () => {
        const jl = $('.job-lang input', context).is(':checked');
        if (jl !== job_lang) {
          load_tlib();
          job_lang = jl;
          const lang = job_lang ? 'en|fr' : 'en|en';
          $('.gt_selector', context).val(lang);
          window.doGTranslate(lang);
        }
      }).on('keydown', (e) => {
        if (13 === e.keyCode) {
          const $input = $('.job-lang input', context);
          $input.prop('checked', !$input.prop('checked'));
          $(e.target).trigger('click');
        }
      });

      $(once('jobboard', '.gt_selector', context)).on('change', (e) => {
        const lang = $(e.target).val();
        job_lang = lang === 'en|fr';
        $('.job-lang input', context).prop('checked', job_lang);
      });

      function navLogout() {
        localStorage.removeItem('currentUser');
        document.cookie = 'currentUser.username=; expires=Thu, 01 Jan 1970 00:00:00 UTC; Path=/;';
        document.cookie = 'currentUser.email=; expires=Thu, 01 Jan 1970 00:00:00 UTC; Path=/;';
        document.cookie = 'currentUser.firstName=; expires=Thu, 01 Jan 1970 00:00:00 UTC; Path=/;';
        document.cookie = 'currentUser.lastName=; expires=Thu, 01 Jan 1970 00:00:00 UTC; Path=/;';
        document.cookie = 'currentUser.id=; expires=Thu, 01 Jan 1970 00:00:00 UTC; Path=/;';
        document.cookie = 'currentUser.token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; Path=/;';
        if (location.pathname === '/account') location.reload(true); else return true;
      }

      function navUserMenu() {
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

      function accountPageChanges(event) {
        // Adjust block visibility
        const $headerLogin = $('#block-workbc-jobboardloginheader');
        const $headerRegister = $('#block-workbc-jobboardregisterheader');
        const $footerLogin = $('#block-workbc-jobboardloginfooter');
        const $footerRegister = $('#block-workbc-jobboardregisterfooter');
        const hash = event.type === 'jobboardlogin' ? '#/dashboard' : window.location.hash
        switch (hash) {
          case '#/login':
            $headerLogin.show();
            $headerRegister.hide();
            $footerLogin.show();
            $footerRegister.hide();
            break;
          case '#/register':
            $headerLogin.hide();
            $headerRegister.show();
            $footerLogin.hide();
            $footerRegister.show();
            break;
          default:
            $headerLogin.hide();
            $headerRegister.hide();
            $footerLogin.hide();
            $footerRegister.hide();
        }

        // Adjust a11y link
        $('#skip-link').attr('href', window.location.hash + '#main-content');
      }

      function closePanel() {
        const offCanvas = $("#off-canvas")[0];
        if (offCanvas) {
          const mmenuApi = offCanvas.mmApi;
          mmenuApi["openPanel"](document.getElementById('mm-1'));
        }
      }
    }
  }
})(Drupal, jQuery, once);

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
