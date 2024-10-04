jQuery(document).ready(function($) {

    $(document).on('click', '.wc-rw-ode-download-xml', function (){

        setTimeout(function (){

            $('.wc-rw-ode-xml-message').empty().append('<p>XML report has been downloaded!</p>');


        }, 2000);


    })

})
