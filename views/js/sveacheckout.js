$(document).ready(function() {
	$('.js-decrease-product-quantity').click(function() {
		window.location.reload();
	});
	$('.js-increase-product-quantity').click(function() {
		window.location.reload();
	});

	$('.checkout.cart-detailed-actions.card-block>.text-sm-center').hide();


	$( "#promo-code>form>button" ).on('click', function(){
		return setTimeout(function() {
			window.location.reload();
		}, 1000);
	});

});
