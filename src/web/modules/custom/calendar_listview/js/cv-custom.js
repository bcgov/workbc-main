(function ($, Drupal) {
  Drupal.behaviors.cv = {
    attach: function (context, settings) {
      $(document).once().ready(function(){
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
        var pager = $(".list-full-view nav.pager").clone();
        if($('.list-full-view div.pager2').length <= 0){
          $(".list-full-view nav.pager").once().after("<div class='pager2'></div>");
          $(".list-full-view div.pager2").once().html(pager);
        }
        
        var dcv = $(".js-drupal-fullcalendar .fc-header-toolbar");
        if(typeof dcv.attr('class') !== "undefined"){
          var pagerCalendarView = dcv.clone();
          if($('.js-drupal-fullcalendar div.pager2').length <= 0){
            $(".fc-view-container").once().after("<div class='pager2'></div>");
            $(".js-drupal-fullcalendar div.pager2").once().html(pagerCalendarView);
            $(".js-drupal-fullcalendar div.pager2 .fc-center").hide();
          }
        }
        
        var dcv = $(".js-drupal-fullcalendar .fc-content-skeleton");
        
        if(typeof dcv.attr('class') !== "undefined"){
          var row = 0;
          /* $(".js-drupal-fullcalendar .fc-content-skeleton table tbody tr").each(function(){
            row++;
            var item = 0;
            $(this).find('td').each(function(){
              item++;
              if(typeof $(this).attr('class') !== "undefined"){
                $('.fc-day-grid .fc-week:nth-child('+row+') td:nth-child('+item+')').addClass("active");
              }
            });
          }); */
        }
        $('.pager2 .fc-next-button').once().click(function(){
          $('.fc-toolbar .fc-next-button:first').trigger("click");
        });
        $('.pager2 .fc-prev-button').once().click(function(){
          $('.fc-toolbar .fc-prev-button:first').trigger("click");
        });
        if (window.innerWidth < 500) {
          $(".fc-day-header.fc-sun").once().text("S");
          $(".fc-day-header.fc-mon").once().text("M");
          $(".fc-day-header.fc-tue").once().text("T");
          $(".fc-day-header.fc-wed").once().text("W");
          $(".fc-day-header.fc-thu").once().text("T");
          $(".fc-day-header.fc-fri").once().text("F");
          $(".fc-day-header.fc-sat").once().text("S");
        }
      });
      
      $(".fc-prev-button").once().append("Previous Month");
      $(".fc-next-button").once().prepend("Next Month");
      $(".fc-tue span").once().append("s");
      $(".fc-thu span").once().append("rs");
      $(".fc-day-grid-event").once().click(function(){
        var content = '';
        var content = $(this).find('.list-view').clone();
        $(".fc-day-grid-event").removeClass("active");
        $(this).addClass("active");
        if($(".calendar-full-view .bottom-buttons").height() > 100){
          $(".calendar-full-view .bottom-buttons").slideUp( "slow", function(){
            $(".calendar-full-view .bottom-buttons").html(content);
            $( ".calendar-full-view .bottom-buttons" ).slideDown( "slow");
          });
        }else{
          $('html, body').animate({ scrollTop: $(document).height() }, 2000);
          $( ".calendar-full-view .bottom-buttons" ).slideUp();
          $(".calendar-full-view .bottom-buttons").html($(this).find('.list-view').clone());
          $( ".calendar-full-view .bottom-buttons" ).slideDown( "slow");
        }
      });
    }
  }
})(jQuery, Drupal);
