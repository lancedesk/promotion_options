document.addEventListener("DOMContentLoaded", function()
{
    const checkForElement = () => {
        const selectCondition = document.getElementById("directorypress-field-input-19");
        const promotionOptionsDiv = document.querySelector('.promotion-options');
        
        if (selectCondition && promotionOptionsDiv) {
            const chargingAllowed = promotionOptionsDiv.getAttribute('data-charging-allowed') === 'true';
            const newListingPrice = parseFloat(promotionOptionsDiv.getAttribute('data-new-listing-price'));

            $(selectCondition).on('select2:select', function (e) {
                var selectedOption = e.params.data.text;
                
                if (selectedOption === "New") {
                    if (chargingAllowed) {
                        /* Add the new listing price to the total and show the new item charge */
                        totalPrice += newListingPrice;
                        document.getElementById('new-item-charge').textContent = `${newListingPrice.toFixed(2)}`;
                        document.querySelector('.new-item-charge').style.display = 'block'; /* Show the new item charge */
                    }
                } else {
                    /* Hide the new item charge and reset the new listing price */
                    document.querySelector('.new-item-charge').style.display = 'none'; /* Hide the new item charge */
                    totalPrice -= newListingPrice; /* Subtract the new listing price from the total */
                }
                updateTotalDisplay();
            });

        } else {
            setTimeout(checkForElement, 100); /* Retry after 100ms */
        }
    };
    checkForElement();

    var promotionOptions = document.querySelectorAll('.promotion-option');
    var checkboxes = document.querySelectorAll('.promotion-option input[type="checkbox"]');
    var totalElement = document.getElementById('promotion-total-amount');
    var totalPrice = 0.0;

    function updateTotalPrice(checkbox) {
        var price = parseFloat(checkbox.getAttribute('data-price'));
        if (checkbox.checked) {
            totalPrice += price;
            checkbox.parentElement.classList.add('selected');
        } else {
            totalPrice -= price;
            checkbox.parentElement.classList.remove('selected');
        }
        updateTotalDisplay();
    }

    function updateTotalDisplay() {
        totalElement.textContent = totalPrice.toFixed(2);
    }
    
    promotionOptions.forEach(function(option) {
        option.addEventListener('click', function(e) {
            if (e.target !== option.querySelector('input[type="checkbox"]')) {
                var checkbox = option.querySelector('input[type="checkbox"]');
                checkbox.checked = !checkbox.checked;
                updateTotalPrice(checkbox);
            }
        });
    });

    checkboxes.forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            updateTotalPrice(checkbox);
        });
    });
});

/* Query to show cities in the ad creation form on country selection */
jQuery(document).ready(function($)
{
    $('#country_dropdown').on('change', function() {
        var country_id = $(this).val();

        $.ajax({
            url: ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'load_cities',
                country_id: country_id
            },
            success: function(response) {
                if (response.success) {
                    var cities = response.data;
                    var city_dropdown = $('#city_dropdown');
                    city_dropdown.empty();
                    city_dropdown.append('<option value="">Select a city</option>');
                    $.each(cities, function(index, city) {
                        city_dropdown.append('<option value="' + city.id + '">' + city.name + '</option>');
                    });
                    $('#city_wrapper').show();
                } else {
                    alert(response.data);
                }
            }
        });
    });
});

/* Query to mark listing as sold and add success message */
jQuery(document).ready(function($)
{
    $('.mark-as-sold').on('click', function() {
        var button = $(this);
        var listing_id = button.data('listing-id');
        var buyer_id = button.data('buyer-id');
        var seller_id = button.data('seller-id');
		
		/* Disable the button */
        button.prop('disabled', true);

        $.ajax({
            url: ajax_object.ajax_url, /* Use the localized ajax_url */
            type: 'POST',
            data: {
                action: 'mark_as_sold',
                listing_id: listing_id,
                buyer_id: buyer_id,
                seller_id: seller_id
            },
            success: function(response) {
                if (response.success) {
                    button.remove();
                    $('<span class="sale-status">Successfully sold item!</span>').insertAfter('.difp-message');
                } else {
                    alert('Failed to mark as sold: ' + response.data);
                }
            },
            error: function() {
                alert('An error occurred.');
            },
			complete: function() {
                /* Re-enable the button after the request is complete */
                button.prop('disabled', false);
            }
        });
    });
});