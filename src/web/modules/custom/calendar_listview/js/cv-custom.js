(function (Drupal, $, once) {
  Drupal.behaviors.cv = {
    attach: function (context, settings) {

      $(document).ready(function(){
        var all_class = $('.view-display-id-block_1 .pager__items').children().length;
        if($('body').hasClass("path-calendar")){
          var cfv = $(".calendar-full-view .fc-body .fc-week");
          if(typeof cfv.attr('class') !== "undefined"){
            var counter = 0;
            $(".calendar-full-view .fc-body .fc-week:last-child .fc-content-skeleton thead tr td").each(function(){
              if($(this).hasClass('fc-other-month')){
                counter++;
              }
              if(counter == 7){
                $(".calendar-full-view .fc-body .fc-week:last-child").addClass("hide");
              }
            });
          }
        }
        var pager = $(".list-full-view div.pager").clone();
        if($('.list-full-view div.pager2').length <= 0){
          $(once("cv", ".list-full-view div.pager")).after("<div class='pager2'></div>");
          $(once("cv", ".list-full-view div.pager2")).html(pager);
        }

        var dcv = $(".js-drupal-fullcalendar .fc-header-toolbar");
        if(typeof dcv.attr('class') !== "undefined"){
          var pagerCalendarView = dcv.clone();
          if($('.js-drupal-fullcalendar div.pager2').length <= 0){
            $(once("cv", ".fc-view-container")).after("<div class='pager2'></div>");
            $(once("cv", ".js-drupal-fullcalendar div.pager2")).html(pagerCalendarView);
            $(".js-drupal-fullcalendar div.pager2 .fc-center").hide();
          }
        }

        $(once('cv2', '.pager2 .fc-next-button')).click(function(){
          $('.fc-toolbar .fc-next-button:first').trigger("click");
        });

        $(once('cv2', '.pager2 .fc-prev-button')).click(function(){
          $('.fc-toolbar .fc-prev-button:first').trigger("click");
        });

        if (window.innerWidth < 500) {
          $(once("cv", ".fc-day-header.fc-sun")).text("S");
          $(once("cv", ".fc-day-header.fc-mon")).text("M");
          $(once("cv", ".fc-day-header.fc-tue")).text("T");
          $(once("cv", ".fc-day-header.fc-wed")).text("W");
          $(once("cv", ".fc-day-header.fc-thu")).text("T");
          $(once("cv", ".fc-day-header.fc-fri")).text("F");
          $(once("cv", ".fc-day-header.fc-sat")).text("S");
        }
      });

      $(once("cv", ".fc-prev-button")).append("Previous Month");
      $(".fc-prev-button").attr("aria-label","Previous Month");
      $(once("cv", ".fc-next-button")).prepend("Next Month");
      $(".fc-next-button").attr("aria-label","Next Month");

      $(once("cv", ".fc-tue span")).append("s");
      $(once("cv", ".fc-thu span")).append("rs");

      $(once("cv", ".fc-day-grid-event")).click(function(){
        var id = $(this).find('.grid-view')[0].id.replace('event-id-', '');
        var events = JSON.parse(settings.fullCalendarView[0].calendar_options).events;

        var content = '';
        for (const key in events) {
          if (events[key].eid == id) {
            content = events[key].des;
          }
        }

        $(".fc-day-grid-event").removeClass("active");
        $(this).addClass("active");
        if($(".calendar-full-view .bottom-buttons").height() > 100){
          $('html, body').animate({ scrollTop: $(document).height() }, 250);
          $(".calendar-full-view .bottom-buttons").slideUp( "slow", function(){
            $(".calendar-full-view .bottom-buttons").html(content);
            $( ".calendar-full-view .bottom-buttons" ).slideDown( "slow");
          });
        }else{
          $('html, body').animate({ scrollTop: $(document).height() }, 250);
          $( ".calendar-full-view .bottom-buttons" ).slideUp();
          $(".calendar-full-view .bottom-buttons").html(content);
          $( ".calendar-full-view .bottom-buttons" ).slideDown( "slow");
        }

        var element = context.querySelector(".list-view");
        if (element != null) {
          element.scrollIntoView(alignToTop);
        }
      });
    }
  }
})(Drupal, jQuery, once);
