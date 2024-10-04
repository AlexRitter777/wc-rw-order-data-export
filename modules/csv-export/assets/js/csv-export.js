jQuery(document).ready(function($) {

    $(document).on('click', '.wc-rw-ode-download-csv', function (){

        setTimeout(function (){

            $('.wc-rw-ode-csv-message').empty().append('<p>CSV report has been downloaded!</p>');


        }, 2000);


    })

})
