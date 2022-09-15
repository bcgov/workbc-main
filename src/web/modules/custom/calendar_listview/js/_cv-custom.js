(function ($, Drupal) {
  Drupal.behaviors.cv = {
    attach: function (context, settings) {
      $(document).once().ready(function(){
        var all_class = $('.view-display-id-block_1 .pager__items').children().length;
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
          $('html, body').animate({ scrollTop: $(document).height() }, 2000);
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
