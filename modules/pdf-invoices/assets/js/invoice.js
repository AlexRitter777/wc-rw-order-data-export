
jQuery(document).ready(function($) {

    // Get all date input elements
    let input_date_elements = $('.wc-rw-data-export-invoice-input');

    // Function to create a new "Remove button"
    function createRemoveButton() {

        return $('<button type="button" class="wc-rw-order-data-export-clear-date-button">✖</button>');

    }

    let button = '<button type="button" class="wc-rw-order-data-export-clear-date-button">✖</button>';

    // Iterate over each input field and add the remove button if the value is not "Not created!"
    input_date_elements.each(function (index, input_date_element){

        let input_date_element_value = $(input_date_element).val();

        if (input_date_element_value !== "Not created!"){

            $(this).parent().append(createRemoveButton());
        }

    })

    // Event listener for the remove button to clear the input value and remove the button itself
    $(document).on('click', '.wc-rw-order-data-export-clear-date-button', function(e) {

        $(this).prev().val('');
        $(this).remove();

    });

    // Add the remove button when the input value changes, only if a button doesn't already exist
    $(input_date_elements).change(function (){

        if($(this).val() !== "" && $(this).parent().has('button').length == 0 ){

            $(this).parent().append(createRemoveButton());

        }

    })


});
