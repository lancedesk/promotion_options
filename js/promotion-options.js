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

/* Nonce in review submission AJAX request */
document.addEventListener('DOMContentLoaded', function() {
    const stars = document.querySelectorAll('.rating-stars input');
    stars.forEach(star => {
        star.addEventListener('change', function() {
            const rating = this.value;
            stars.forEach(s => s.nextElementSibling.classList.toggle('selected', s.value <= rating));
        });
    });

    /* Handle form submission via AJAX */
    document.querySelector('#review-form form').addEventListener('submit', function(e) {
        e.preventDefault(); /* Prevent the form from submitting normally */

        const reviewName = document.querySelector('input[name="review-name"]').value;
        const reviewEmail = document.querySelector('input[name="review-email"]').value;
        const rating = document.querySelector('input[name="rating"]:checked') ? document.querySelector('input[name="rating"]:checked').value : '';
        const comment = document.querySelector('#comment').value;
        const listingId = document.querySelector('input[name="listing_id"]').value;
        
        /* Debug section 
            console.log('Review Name:', reviewName);
            console.log('Review Email:', reviewEmail);
            console.log('Rating:', rating);
            console.log('Comment:', comment);
            console.log('Listing ID:', listingId);
        */

        jQuery.post(ajax_object.ajax_url, {
            action: 'submit_review',
            nonce: ajax_object.nonce,
            'review-name': reviewName,
            'review-email': reviewEmail,
            'rating': rating,
            'comment': comment,
            'listing_id': listingId
        }, function(response) {
            if (response.success) {
                /* alert(response.data.message); */
                /* Cleanup on successful submission */
				/* Hide the review form */
				document.querySelector('#review-form').style.display = 'none';

                /* Update the reviews section with the new reviews */
                const reviewsContainer = document.querySelector('#reviews-container');
                reviewsContainer.innerHTML = response.data.reviews_html;
            } else {
                alert(response.data.message);
            }
        });
    });
});

document.addEventListener('DOMContentLoaded', function() {
    const stars = document.querySelectorAll('.rating-stars input');
    const labels = document.querySelectorAll('.rating-stars label');
    
    stars.forEach(star => {
        star.addEventListener('change', function() {
            const rating = this.value;
            labels.forEach(label => {
                label.classList.toggle('selected', label.getAttribute('for').slice(-1) <= rating);
            });
        });
    });

    /* Rating form stars hover effect */
    const ratingStars = document.querySelector('.rating-stars');
    ratingStars.addEventListener('mouseover', function(e) {
        if (e.target.tagName === 'LABEL') {
            const rating = e.target.getAttribute('for').slice(-1);
            labels.forEach(label => {
                label.classList.toggle('hover', label.getAttribute('for').slice(-1) <= rating);
            });
        }
    });

    ratingStars.addEventListener('mouseout', function() {
        labels.forEach(label => {
            label.classList.remove('hover');
        });
    });
});