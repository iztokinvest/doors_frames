const frame_notifier = new AWN({
	durations: {
		global: 5000,
		position: "bottom-right",
	},
});

jQuery(document).ready(function ($) {
	$(".price-input").on("change", function () {
		var productId = $(this).data("id");
		var newPrice = $(this).val();
		var priceType = $(this).data("type");

		$.ajax({
			url: ajaxurl,
			type: "POST",
			data: {
				action: "update_product_price",
				product_id: productId,
				new_price: newPrice,
				price_type: priceType,
			},
			success: function (response) {
				if (response.success) {
					frame_notifier.success(`Цената е променена.`);
				} else {
					frame_notifier.alert(`Цената не е променена.`);
				}
			},
		});
	});

	// Handle modal open button click
	$(".open-modal").on("click", function () {
		var productId = $(this).data("id");

		// Fetch data from the server
		$.ajax({
			url: ajaxurl,
			type: "POST",
			data: {
				action: "fetch_frame_prices",
				product_id: productId,
			},
			success: function (response) {
				if (response.success) {
					$("#modal-body").html(response.data);
					$("#frameModal").show();
				} else {
					frame_notifier.alert("Failed to load data.");
				}
			},
		});
	});

	// Handle modal close
	$(".close, .btn-close").on("click", function () {
		$("#frameModal").hide();
	});

	// Close modal when clicking outside of the modal content
	$(window).on("click", function (event) {
		if ($(event.target).is("#frameModal")) {
			$("#frameModal").hide();
		}
	});

	// Save changes in modal
	$("#save-modal-prices").on("click", function () {
		var productId = 66;
		var price1 = $("#price1-input").val();
		var price2 = $("#price2-input").val();
		var price3 = $("#price3-input").val();

		console.log(productId);

		$.ajax({
			url: ajaxurl,
			type: "POST",
			data: {
				action: "update_frame_prices",
				product_id: productId,
				price1: price1,
				price2: price2,
				price3: price3,
			},
			success: function (response) {
				if (response.success) {
					frame_notifier.success("Цените са променени.");
					$("#frameModal").hide();
				} else {
					frame_notifier.alert("Цените не са променени.");
				}
			},
		});
	});
});
