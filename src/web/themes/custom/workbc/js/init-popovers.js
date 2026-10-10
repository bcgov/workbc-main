(function (Drupal, $, once) {
  ("use strict");

  // this set of functions is intended to cause popovers to close if the user clicks anywhere outside of them
  // see https://stackoverflow.com/a/69602400/495000
  const managePopoverClosure = function (context) {
    $(document).on('click', function (event) {
      const $target = $(event.target);
      // Do nothing if there was a click on popover content
      if ($target.hasClass('popover') || $target.closest('.popover').length) {
        return;
      }
      $('[data-bs-toggle="popover"]', context).each(function () {
        const $popover = $(this);
        if (
          !$popover.is(event.target) &&
          $popover.has(event.target).length === 0 &&
          $('.popover').has(event.target).length === 0
        ) {
          $popover.popover('hide');
        }
      });
    });

    $(document).on('keyup', function(event) {
      if (event.key == "Escape") {
        $('[data-bs-toggle="popover"]', context).each(function () {
          $(this).popover('hide');
        });
      }
      if (event.key == "Enter" && $(event.target).is('[data-bs-toggle="popover"]') && $(event.target).children('div.popover').length == 0) {
        $(event.target).popover('show');
      }
    });

    $(document).on('blur', '[data-bs-toggle="popover"]', function(event) {
      if (!event.relatedTarget || $(event.relatedTarget).parents('.popover').length == 0) {
        $(this).popover('hide');
      }
    });
  }

  Drupal.behaviors.initPopoverBehaviour = {
    attach: function (context, settings) {
      $(once('initPopoverBehaviour', '.info-tooltip[data-bs-toggle="popover"]', context)).each(function() {
        const $element = $(this);
        const role = $element.data('bs-content').includes('<a href') ? 'dialog' : 'tooltip';
        $(document).ready(function() {
          managePopoverClosure(context);
          $element.on('shown.bs.popover', function (event) {
            const $target = $(event.target);
            $('#tooltip-live-region').html($target.attr('data-bs-original-title') + $target.attr('data-bs-content'));
          }).on('hidden.bs.popover', function (event) {
            $('#tooltip-live-region').text('');
          }).popover({
            template: `<div class="popover" role="${role}"><div class="popover-arrow"></div><h3 class="popover-header"></h3><div class="popover-body"></div></div>`
          });
        });
      });
    },
  };

  // Non-modal visibility avoids Bootstrap Modal's page scroll lock and padding changes.
  Drupal.behaviors.initInfoDialogBehaviour = {
    attach: function (context, settings) {
      once('initInfoDialogBehaviour', '[data-workbc-info-dialog]', context).forEach(function (toggle) {
        const dialogEl = document.querySelector(toggle.dataset.workbcInfoDialog);
        if (!dialogEl) {
          return;
        }
        const content = dialogEl.querySelector('.modal-content');
        const isShown = () => dialogEl.classList.contains('show');
        // Pinned dialogs (opened by focus/click) stay open when the pointer leaves.
        let pinned = false;
        let hoverTimer = null;
        let popper = null;

        const containsTarget = function (target) {
          return target && (toggle.contains(target) || dialogEl.contains(target));
        };
        const cancelHoverHide = function () {
          clearTimeout(hoverTimer);
          hoverTimer = null;
        };
        const dialog = {
          show: function () {
            if (isShown()) {
              return;
            }
            dialogEl.style.display = 'block';
            dialogEl.classList.add('show');
            dialogEl.setAttribute('aria-hidden', 'false');
            toggle.setAttribute('aria-expanded', 'true');
            popper = Popper.createPopper(toggle, dialogEl.querySelector('.modal-dialog'), {
              placement: 'bottom',
              strategy: 'fixed',
              modifiers: [
                { name: 'offset', options: { offset: [0, 8] } },
                // The mobile filter panel clips descendants, even with fixed positioning.
                { name: 'flip', options: { rootBoundary: 'viewport', boundary: 'clippingParents', altBoundary: true, padding: 8 } },
                { name: 'preventOverflow', options: { rootBoundary: 'viewport', boundary: 'clippingParents', altBoundary: true, padding: 8 } },
                { name: 'arrow', options: { element: '.popover-arrow', padding: 8 } },
              ],
            });
          },
          hide: function () {
            if (!isShown()) {
              return;
            }
            if (popper) {
              popper.destroy();
              popper = null;
            }
            dialogEl.classList.remove('show');
            dialogEl.style.display = 'none';
            dialogEl.setAttribute('aria-hidden', 'true');
            pinned = false;
            cancelHoverHide();
            toggle.setAttribute('aria-expanded', 'false');
          },
        };
        const scheduleHoverHide = function () {
          cancelHoverHide();
          if (pinned) {
            return;
          }
          hoverTimer = setTimeout(function () {
            if (!pinned && !toggle.matches(':hover') && !content.matches(':hover')) {
              dialog.hide();
            }
          }, 300);
        };
        toggle.addEventListener('mouseenter', function () {
          cancelHoverHide();
          dialog.show();
        });
        toggle.addEventListener('mouseleave', scheduleHoverHide);
        content.addEventListener('mouseenter', cancelHoverHide);
        content.addEventListener('mouseleave', scheduleHoverHide);

        toggle.addEventListener('focus', function () {
          pinned = true;
          cancelHoverHide();
          dialog.show();
        });

        toggle.addEventListener('click', function (event) {
          event.preventDefault();
          event.stopPropagation();
          pinned = true;
          cancelHoverHide();
          dialog.show();
        });

        // Close when clicking anywhere outside the dialog and its toggle.
        document.addEventListener('click', function (event) {
          if (
            isShown() &&
            !containsTarget(event.target)
          ) {
            dialog.hide();
          }
        });

        // Let the browser choose the next focus target before closing.
        const closeOnFocusOut = function (event) {
          if (isShown() && !containsTarget(event.relatedTarget)) {
            dialog.hide();
          }
        };
        dialogEl.addEventListener('focusin', function () {
          pinned = true;
          cancelHoverHide();
        });
        dialogEl.addEventListener('focusout', closeOnFocusOut);
        toggle.addEventListener('focusout', closeOnFocusOut);

        document.addEventListener('keydown', function (event) {
          if (event.key === 'Escape' && isShown()) {
            dialog.hide();
          }
        });

      });
    },
  };

})(Drupal, jQuery, once);
