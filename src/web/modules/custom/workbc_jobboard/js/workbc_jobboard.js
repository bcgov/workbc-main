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
    }
  }
})(jQuery, Drupal);

