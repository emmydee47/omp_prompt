var $ = jQuery;
$('#ship-to-different-address-checkbox').click();
//$('.input-text:not(#order_comments, #datepicker)').prop('disabled', true);
 $('.input-text').each(function(){
     $(this).val("");
 })
$('#billing_customer').change(function(){
    var thisval = $(this).val();
    $('#billing_first_name').focus();
    $('#billing_first_name').attr('value', $('#billing_customer option:selected').data('first_name'));
    $('#billing_last_name').focus();
    $('#billing_last_name').attr('value', $('#billing_customer option:selected').data('lastname'));
    $('#billing_company').focus();
    $('#billing_company').attr('value', $('#billing_customer option:selected').data('company'));
    $('#billing_country').focus();
    $('#billing_country').attr('value', $('#billing_customer option:selected').data('country'));
    $('#billing_address_1').focus();
    $('#billing_address_1').attr('value', $('#billing_customer option:selected').data('street_address'));
   // $('#billing_address_1').attr('value',$('#billing_customer option:selected').data('street_address'));

    $('#billing_city').attr('value', $('#billing_customer option:selected').data('city'));
    $('#billing_state').attr('value', $('#billing_customer option:selected').data('state'));
    $('#billing_country').attr('value', $('#billing_customer option:selected').data('country'));
    $('#billing_postcode').attr('value', $('#billing_customer option:selected').data('postal_code'));
    $('#billing_phone').attr('value', $('#billing_customer option:selected').data('phone'));
    $('#billing_email').attr('value', $('#billing_customer option:selected').data('email'));


    $('#shipping_first_name').attr('value', $('#billing_customer option:selected').data('first_name'));
    $('#shipping_last_name').attr('value', $('#billing_customer option:selected').data('lastname'));
    $('#shipping_company').attr('value', $('#billing_customer option:selected').data('company'));
    $('#shipping_country').attr('value', $('#billing_customer option:selected').data('country'));
    $('#shipping_address_1').attr('value', $('#billing_customer option:selected').data('street_address'));
    $('#shipping_city').attr('value', $('#billing_customer option:selected').data('city'));
    $('#shipping_state').attr('value', $('#billing_customer option:selected').data('state'));
    $('#shipping_country').attr('value', $('#billing_customer option:selected').data('country'));
    $('#shipping_postcode').attr('value', $('#billing_customer option:selected').data('postal_code'));

    // $('#billing_customer2 option:selected').data('first_name'));
    // $('#billing_customer2 option:selected').data('lastname'));
    // $('#billing_customer2 option:selected').data('company'));
    // $('#billing_customer2 option:selected').data('country'));
    // $('#billing_customer2 option:selected').data('street_address'));
    // $('#billing_customer2 option:selected').data('city'));
    // $('#billing_customer2 option:selected').data('state'));
    // $('#shipping_country').val($('#billing_customer2 option:selected').data('country'));
    // $('#shipping_postcode').val($('#billing_customer2 option:selected').data('postal_code'));



    var data = {
        action: 'get_customer_details',
    };
    var ajaxurl = 'http://localhost/omprompt_ecom/wp-admin/admin-ajax.php' + ''; 
    $.post(ajaxurl, data, function(response) {
        var customerDetails = response.substring(0, response.lastIndexOf('0'));
        var customerArray = $.parseJSON(customerDetails);
        var selectedCustomers = [];
        $.each(customerArray, function(index, val){
            if(val.company_code==thisval){
                selectedCustomers.push(val);
            }
        });
        $('#billing_customer2').empty();
        var theOptions = "";
        $.each(selectedCustomers, function(index, val){
           theOptions = '<option data-first_name="'+val.firstname+'" data-lastname="'+val.lastname+'"';
           theOptions += ' data-company="'+val.company_name+'" data-street_address="'+val.street_address+'"';
           theOptions += ' data-city="'+val.city+'" data-postal_code="'+val.postal_code+'"';
           theOptions += ' data-country="'+val.country+'"  data-state="'+val.state+'"'; 
           theOptions += ' data-phone="'+val.phone+'"  data-email="'+val.email_address+'">'+val.street_address+', '+val.city+'</option>';
            $('#billing_customer2').append(theOptions);
        });
        console.log(selectedCustomers);
    });
})



$('#billing_customer2').change(function(){
    var thisval = $(this).val();
    
    $('#shipping_first_name').val($('#billing_customer2 option:selected').data('first_name'));
    $('#shipping_last_name').val($('#billing_customer2 option:selected').data('lastname'));
    $('#shipping_company').val($('#billing_customer2 option:selected').data('company'));
    $('#shipping_country').val($('#billing_customer2 option:selected').data('country'));
    $('#shipping_address_1').val($('#billing_customer2 option:selected').data('street_address'));
    $('#shipping_city').val($('#billing_customer2 option:selected').data('city'));
    $('#shipping_state').val($('#billing_customer2 option:selected').data('state'));
    $('#shipping_phone').val($('#billing_customer2 option:selected').data('phone'));
    $('#shipping_postcode').val($('#billing_customer2 option:selected').data('postal_code'));

})
function getCustomerData(){
    var data = {
        action: 'get_customer_details',
    };
    var ajaxurl = 'http://localhost/omprompt_ecom/wp-admin/admin-ajax.php' + ''; 
    $.post(ajaxurl, data, function(response) {
        var candidateDetails = response.substring(0, response.lastIndexOf('0'));
        console.log(candidateDetails)
    });
}
