// --------------------------------------------------------------------------------------------------
// Contact form 7 tracking
// ** To use this replace the FORMID with the id of your contact form and then replace the FORM NAME with the form name.
// ** Use as many if statement as required to cover off your forms.
// --------------------------------------------------------------------------------------------------
// document.addEventListener('wpcf7mailsent', function (event) {
//     if ('FORMID' == event.detail.contactFormId) {
//         // ga('send', 'event', 'Contact Form', 'Submit', 'Contact Form A');
//         gtag('event', 'Course Form Submission', { 'event_category': 'FORM NAME Submission', 'event_label': 'FORM NAME Submission ' + window.location });
//     }
//     else if ('FORMID' == event.detail.contactFormId) {
//         // ga('send', 'event', 'Contact Form', 'Submit', 'Contact Form B');
//         gtag('event', 'Contact Form Submission', { 'event_category': 'FORM NAME Submission', 'event_label': 'FORM NAME Submission' });
//     }
// }, false);

// --------------------------------------------------------------------------------------------------
// HTML Forms Tracking
// ** To use this replace the FORMID with the id of your contact form and then replace the FORM NAME with the form name.
// ** Use as many if statement as required to cover off your forms.
// --------------------------------------------------------------------------------------------------
// var forms = document.querySelectorAll('.hf-form');

// forms.forEach(function(el) {
//     el.addEventListener('hf-submit', function (event) {
//         var form = event.target;
//         var formID = form.getAttribute('data-id');

//         if(formID == 'FORMID') { // change for your form ID
//             gtag('event', 'conversion', {'event_category': 'FORM NAME','event_label': 'Successful FORM NAME Enquiry'});
//         } else if(formID == 'FORMID') { // change for your form ID
//             gtag('event', 'conversion', {'event_category': 'FORM NAME','event_label': 'Successful FORM NAME Enquiry'});
//         }
//     }, false);
// });