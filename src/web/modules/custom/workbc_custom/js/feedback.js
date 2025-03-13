/// GDX Snowplow Feedback Code
let dismissed_pause = 0;
let responded_pause = 0;

(function ($, Drupal, once) {
  Drupal.behaviors.feedback = {
    attach: function (context, settings) {
      $(once('feedback', document.body)).each(function() {
        // Add function to test for visibility.
        // https://stackoverflow.com/a/40658647/209184
        $.fn.isInViewport = function () {
          let elementTop = $(this).offset().top;
          let elementBottom = elementTop + $(this).outerHeight();

          let viewportTop = $(window).scrollTop();
          let viewportBottom = viewportTop + $(window).height();

          return elementBottom > viewportTop && elementTop < viewportBottom;
        };

        dismissed_pause = settings.feedback.settings.dismissed_pause;
        responded_pause = settings.feedback.settings.responded_pause;

        // Check to see if Snowplow is present on the page. If not, don't display the feedback box
        if (window.snowplow) {
          feedback_load();

          // Add the container to the body.
          const container = document.createElement('div');
          container.id = 'feedback_wrapper';
          document.body.appendChild(container);

          // Set up the activation triggers:
          // - Upon clicking on given selectors if any.
          if (settings.feedback.triggers.click_selector) {
            $(document).on('click', settings.feedback.triggers.click_selector, feedback_show);
          }

          // $(document).on('click', '.cta > .ng-star-inserted > a', feedback_show);
          // $(document).on('click', 'a', feedback_show);

          setTimeout(() => {
            $('lib-jb-a-link a').on('click', null, feedback_show);
          }, 1000);

          // trigger feedback when Job title is clicked
          // $(document).on('click', '.job-info > .title > a.ng-star-inserted', feedback_show);

          // - Upon scrolling to page bottom if specified.
          if (settings.feedback.triggers.scroll) {
            $(window).scroll(function () {
              if ($('.footer').isInViewport()) {
                feedback_show();
              }
            });
          }

          // - After being on the page for a given timeout if anu.
          if (settings.feedback.triggers.timeout) {
            window.setTimeout(feedback_show, settings.feedback.triggers.timeout);
          }

        }
      });
    }
  }
})(jQuery, Drupal, once);

// Define list of options in Thumbs Up response (max 32 characters each)
let up_list = ['Right amount of information', 'Other', 'Reliable content', 'Straightforward',  'Search function', 'Smooth experience', 'Help/support', 'Fast loading times', 'Engaging visuals', 'Accessible design'];
// Define text to display after clicking thumbs up
let up_text = 'Awesome! What did you like about the website?';
// Define list of options in Thumbs Down response (max 32 characters each)
let down_list = ['Not straightforward', 'Confusing layout', 'Not enough information', 'Mobile experience', 'Unhelpful search', 'Lack of support', 'Other', 'Unreliable content', "Links don't work", 'Slow loading times', 'Distracting visuals'];
// Define text to display after clicking thumbs down
let down_text = 'We\'re sorry to hear that. How can we improve?';

// Define list of options in Neutral response
let neutral_list = ['Better search functionality', 'Other', 'More information needed', 'More intuitive', 'Improve mobile experience', 'Faster loading', 'More support options', 'More engaging visuals', 'Better accessibility', 'Improve content reliability'];
let neutral_text = "We appreciate your feeback. How can we make your experience better?";

// How many times did we show / submit the modal?
let show_count = 0;
let submit_count = 0;

// Define Feedback box to display
let feedback_box = `<div class="feedback_box" id="feedback_box">
  <div class="feedback_head">
    <div class="feedback_corner" id="feedback_corner">
      <a href="#" onclick="feedback_close();return false;">
        <img src="/modules/custom/workbc_custom/icons/Cross_icon.svg" alt="Close" title="Close">
      </a>
    </div>
    <h3 id="feedback_title">We'd like to hear from you</h3>
  </div>
  <div class="feedback_body" id="feedback_body">
    <h4>How was your experience with the WorkBC.ca website?</h4>
  </div>
  <table class="feedback_action" id="feedback_action">
    <tr>
      <td class="feedback_item red" id="rating_up">
        <a href="#" onclick="feedback_thumb('rating_1');return false;">
          <img src="/modules/custom/workbc_custom/icons/NotGreat_1.svg" alt="Not Great 1" title="Not Great"/>
        </a>
      </td>
      <td class="feedback_item red" id="rating_up">
        <a href="#" onclick="feedback_thumb('rating_2');return false;">
          <img src="/modules/custom/workbc_custom/icons/NotGreat_2.svg" alt="Not Great 2" title="Not Great 2"/>
        </a>
      </td>
      <td class="feedback_item yellow" id="rating_neutral">
        <a href="#" onclick="feedback_thumb('rating_3');return false;">
          <img src="/modules/custom/workbc_custom/icons/Neutral_3.svg" alt="Neutral" title="Neutral"/>
        </a>
      </td>
      <td class="feedback_item green" id="rating_great_4">
        <a href="#" onclick="feedback_thumb('rating_4');return false;">
          <img src="/modules/custom/workbc_custom/icons/Great_4.svg" alt="Great" title="Great"/>
        </a>
      </td>
      <td class="feedback_item green" id="rating_great_5">
        <a href="#" onclick="feedback_thumb('rating_5');return false;">
          <img src="/modules/custom/workbc_custom/icons/Great_5.svg" alt="Great" title="Great"/>
        </a>
      </td>
    </tr>
    <tr>
      <td class="feedback_legend left" colspan="2">Not Great</td>
      <td>Neutral</td>
      <td class="feedback_legend right" colspan="2">Great</td>
  </table>
</div>`;

// Choose thumbs up or down then present list of items and send initial Snowplow call
function feedback_thumb(selected) {
  switch(selected) {
    case "rating_1":
      feedback_list = down_list;
      feedback_text = down_text;
      feedback_action = 'Not Great 1';
      feedback_selected = '<span class="red"><img src="/modules/custom/workbc_custom/icons/NotGreat_1.svg" alt="Not Great"/><br/>Not Great</span>';
      break;
    case "rating_2":
      feedback_list = down_list;
      feedback_text = down_text;
      feedback_action = 'Not Great 2';
      feedback_selected = '<span class="red"><img src="/modules/custom/workbc_custom/icons/NotGreat_2.svg" alt="Not Great"/><br/>Not Great</span>';
      break;
    case "rating_3":
      feedback_list = neutral_list;
      feedback_text = neutral_text;
      feedback_action = 'Neutral 3';
      feedback_selected = '<span class="yellow"><img src="/modules/custom/workbc_custom/icons/Neutral_3.svg" alt="Neutral"/><br/>Neutral</span>';
      break;
    case "rating_4":
      feedback_list = up_list;
      feedback_text = up_text;
      feedback_action = 'Great 4';
      feedback_selected = '<span class="green"><img src="/modules/custom/workbc_custom/icons/Great_4.svg" alt="Great"/><br/>Great</span>';
      break;
    case "rating_5":
      feedback_list = up_list;
      feedback_text = up_text;
      feedback_action = 'Great 5';
      feedback_selected = '<span class="green"><img src="/modules/custom/workbc_custom/icons/Great_5.svg" alt="Great"/><br/>Great</span>';
      break;
  }

  // Rewrite the body for list feedback
  document.getElementById("feedback_body").innerHTML +='<div class="feedback_selected">' + feedback_selected + '</div><div id="feedback_list" class="feedback_list"></div> <div id="feedback_submit" class="feedback_submit"><div class="feedback_back"><a href="#" onclick="feedback_back();return false;">Back</a></div><button class="feedback_submit_button" onclick="feedback_submit()">Submit</button></div>';
  // hide the thumbs up and thumbs down buttons
  document.getElementById("feedback_action").innerHTML ='';
  // add the list items
  document.getElementById("feedback_list").innerHTML = feedback_text+'<br/>';
  for (let i=0; i<feedback_list.length; i++) {
    document.getElementById("feedback_list").innerHTML += '<button class="feedback_list_item" onclick="feedback_list_select(this.id);" value="'+ feedback_list[i] +'" id="feedback_list_item_'+i+'">' + feedback_list[i] +'</button>';
  }

  setFeedbackPause(responded_pause);

  // Send Snowplow call of either "Thumbs Up" or "Thumbs Down". When a list is selected we'll also include the list of selected options.
  // Note that later there will be more tracking fields added to this call.
  window.snowplow('trackSelfDescribingEvent', {
    schema: 'iglu:ca.bc.gov.feedback/feedback_action/jsonschema/1-0-0',
    data: {
      action: feedback_action
    }
  });
}

// Toggle selection of feedback list options
function feedback_list_select(id) {
  if (jQuery('#' + id).hasClass('button_selected'))
    jQuery('#' + id).removeClass('button_selected');
  else {
    jQuery('#' + id).addClass('button_selected');
  }
}

// Submit feedback (including Snowplow call and thank you message)
function feedback_submit() {
  // Send Snowplow call of either "Thumbs Up List" or "Thumbs Down List". Along with a list is selected we'll also include the list of selected options.
  // Note that later there will be more tracking fields added to this call.
  let feedback_selected_items = document.getElementsByClassName('feedback_list_item button_selected');
  let feedback_selected_list = [];
  for(var i = 0; i < feedback_selected_items.length; i++) {
    feedback_selected_list.push(feedback_selected_items[i].value);
  }
  window.snowplow('trackSelfDescribingEvent', {
    schema: 'iglu:ca.bc.gov.feedback/feedback_action/jsonschema/1-0-0',
    data: {
      action: feedback_action + ' List',
      list: feedback_selected_list
    }
  });
  document.getElementById("feedback_title").innerHTML = 'Your feedback is valuable';
  document.getElementById("feedback_body").innerHTML = '<div class="feedback_thankyou"><h4>Thank you!</h4><img src="/modules/custom/workbc_custom/icons/Thankyou_illustration.svg" alt="Thank you"><p>Your feedback will help us improve WorkBC.ca website.</p></div>';
  submit_count++;
}

// When initial loading the form send a Snowplow call and reset the form
function feedback_load() {
  window.snowplow('trackSelfDescribingEvent', {
    schema: 'iglu:ca.bc.gov.feedback/feedback_action/jsonschema/1-0-0',
    data: {
      action: 'Load'
    }
  });
  // Don't show the modal here because we show based on triggers.
}

// When clicking "Back" send a Snowplow call and reset the form
function feedback_back() {
  window.snowplow('trackSelfDescribingEvent', {
    schema: 'iglu:ca.bc.gov.feedback/feedback_action/jsonschema/1-0-0',
    data: {
      action: 'Back'
    }
  });
  feedback_reset();
}

// Reset the form either on load or when hitting "Back"
function feedback_reset() {
  document.getElementById("feedback_wrapper").innerHTML = feedback_box;
}

// Hide the form
function feedback_close() {
  // only set pause if not already paused, otherwise it would overwrite responded pause
  if (!isFeedbackPaused()) {
    setFeedbackPause(dismissed_pause);
  }
  window.snowplow('trackSelfDescribingEvent', {
    schema: 'iglu:ca.bc.gov.feedback/feedback_action/jsonschema/1-0-0',
    data: {
      action: 'Close'
    }
  });
  document.getElementById("feedback_wrapper").innerHTML = "";
}

// Show modal according to rules of showing.
function feedback_show() {
  alert('HI');
  if (!isFeedbackPaused()) {
    if (show_count < 1) {
      feedback_reset();
      show_count++;
    }
  }
}

function isFeedbackPaused() {
  const d = new Date();
  let pause = window.localStorage.getItem("workbc_feedback_pause");
  if (pause == null) {
    return false;
  }
  return d.getTime() < pause;
}

function setFeedbackPause(days) {
  const d = new Date();
  d.setDate(d.getDate() + days);
  window.localStorage.setItem("workbc_feedback_pause", d.getTime());
}
