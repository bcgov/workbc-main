(function ($, Drupal, once) {
    ("use strict");
    Drupal.behaviors.iconAppend = {
      attach: function (context, settings) {
        // the second parameter must be a selector specific to the content this script applies to, to ensure it's loaded after the content in the case the content is lazy loaded by Drupal
        once('iconAppendAppend', '.plan-careercareer-trek-videos .node-page-content .js-form-item-search-api-fulltext', context).forEach(function (element) {
          $(element).append(
            `<span><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18"><path fill="#333" d="M17.82,17.2451613 C18.06,16.8967742 18.06,16.4903226 17.76,16.2 L12.66,11.2645161 C13.68,10.1032258 14.22,8.59354839 14.22,6.90967742 C14.22,3.07741935 11.04,0 7.14,0 C3.24,0 0,3.07741935 0,6.90967742 C0,10.7419355 3.18,13.8193548 7.14,13.8193548 C8.82,13.8193548 10.38,13.2387097 11.64,12.3096774 L16.74,17.2451613 C16.86,17.3612903 17.1,17.4774194 17.28,17.4774194 C17.46,17.4774194 17.64,17.4193548 17.82,17.2451613 Z M12.78,6.8516129 C12.78,9.87096774 10.2,12.3096774 7.14,12.3096774 C4.02,12.3096774 1.5,9.87096774 1.5,6.8516129 C1.5,3.83225806 4.02,1.39354839 7.14,1.39354839 C10.26,1.39354839 12.78,3.83225806 12.78,6.8516129 Z"/></svg></span>`
          );
        });
        once('iconAppend', '.plan-careercareer-trek-videos .node-page-content .js-form-item-search-api-fulltext span', context).forEach(function (element) {
          element.onclick = function () {
            $(`.plan-careercareer-trek-videos .node-page-content input[value="Apply Filters"]`).trigger("click");
          };
        });

      once('iconAppendDate','.career-profile-content .block-workbc-jobboard .job-footer .job-post-date', context).forEach(function (element) {
          $(element).prepend(
            `<span><svg width="12" height="24" viewBox="0 0 13 15" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2.625 1.2251C2.625 0.760254 3.00781 0.350098 3.5 0.350098C3.96484 0.350098 4.375 0.760254 4.375 1.2251V2.1001H7.875V1.2251C7.875 0.760254 8.25781 0.350098 8.75 0.350098C9.21484 0.350098 9.625 0.760254 9.625 1.2251V2.1001H10.9375C11.6484 2.1001 12.25 2.70166 12.25 3.4126V4.7251H0V3.4126C0 2.70166 0.574219 2.1001 1.3125 2.1001H2.625V1.2251ZM12.25 5.6001V13.0376C12.25 13.7759 11.6484 14.3501 10.9375 14.3501H1.3125C0.574219 14.3501 0 13.7759 0 13.0376V5.6001H12.25Z" fill="#2E6AB0"/></svg></span>`
          );
      });



      },
    };
  
  })(jQuery, Drupal, once);