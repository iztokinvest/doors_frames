const datepickers = document.querySelectorAll(".datepicker-input");
const frame_notifier = new AWN({
	durations: {
		global: 5000,
		position: "bottom-right",
	},
});

(function () {
	Datepicker.locales.bg = {
		days: ["Неделя", "Понеделник", "Вторник", "Сряда", "Четвъртък", "Петък", "Събота"],
		daysShort: ["Нед", "Пон", "Вто", "Сря", "Чет", "Пет", "Съб"],
		daysMin: ["Нд", "Пн", "Вт", "Ср", "Чт", "Пт", "Сб"],
		months: [
			"Януари",
			"Февруари",
			"Март",
			"Април",
			"Май",
			"Юни",
			"Юли",
			"Август",
			"Септември",
			"Октомври",
			"Ноември",
			"Декември",
		],
		monthsShort: ["Ян", "Фев", "Мар", "Апр", "Май", "Юни", "Юли", "Авг", "Сеп", "Окт", "Ное", "Дек"],
	};
})();

function initializeDatepickers() {
	jQuery(".datepicker-input").each(function () {
		const datepicker = new Datepicker(this, {
			format: "dd/mm/yyyy",
			daysOfWeekHighlighted: [6, 0],
			autohide: true,
			weekStart: 1,
			language: "bg",
		});
	});
}
initializeDatepickers();

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
					initializeDatepickers();
				} else {
					frame_notifier.warning("Няма добавени цени на каси за този продукт.");
				}
			},
		});
	});

	// Handle modal close
	$(".close, .btn-close").on("click", function () {
		$("#frameModal").hide();
	});

	// Save changes in modal
	$("#save-modal-prices").on("click", function () {
		var ajaxRequests = [];

		$(".frame-id").each(function () {
			var frameId = $(this).data("id");
			var price = $(this).find(".frame-price").val();
			var promo_price = $(this).find(".frame-promo-price").val();
			var image = $(this).find(".frame-image").val();
			var description = $(this).find(".frame-description").val();
			var startDate = $(this).find(".frame-start-date").val();
			var endDate = $(this).find(".frame-end-date").val();

			var request = $.ajax({
				url: ajaxurl,
				type: "POST",
				data: {
					action: "update_frame_prices",
					frame_id: frameId,
					frame_price: price,
					frame_promo_price: promo_price,
					frame_image: image,
					frame_description: description,
					frame_start_date: startDate,
					frame_end_date: endDate,
				},
			});

			ajaxRequests.push(request);
		});

		$.when.apply($, ajaxRequests).then(function () {
			var allSuccessful = true;
			for (var i = 0; i < arguments.length; i++) {
				var response = arguments[i][0];
				if (!response.success) {
					allSuccessful = false;
					break;
				}
			}

			if (allSuccessful) {
				frame_notifier.success("Цените са променени.");
			} else {
				frame_notifier.alert("Цените не са променени.");
			}

			$("#frameModal").hide();
		});
	});
});
