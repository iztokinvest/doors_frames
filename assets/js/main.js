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
		const productId = $(this).data("id");
		const newPrice = $(this).val();
		const priceType = $(this).data("type");

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
		const productId = $(this).data("id");

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

	$(document).on("click", "#add-new-frame", function () {
		$("#new-frame-table").show();
		$("#new-frame-table tbody").append(`
			<tr class="new-frame" data-id="${$(this).data("id")}">>
				<td><input type="text" class="form-control new-frame-image" placeholder="Изображение"></td>
				<td><textarea class="form-control new-frame-description" placeholder="Описание"></textarea></td>
				<td><input type="number" step="0.01" class="form-control new-frame-price" placeholder="Цена"></td>
				<td><input type="number" step="0.01" class="form-control new-frame-promo-price" placeholder="Промоция"></td>
				<td><input required type="text" class="form-control datepicker-input new-frame-start-date" placeholder="Начална дата" /></td>
				<td><input required type="text" class="form-control datepicker-input new-frame-end-date" placeholder="Крайна дата" /></td>
			</tr>
    `);
		initializeDatepickers();
	});

	// Save changes in modal
	$("#save-modal-prices").on("click", function () {
		const ajaxRequests = [];

		$(".frame-id").each(function () {
			const frameId = $(this).data("id");
			const price = $(this).find(".frame-price").val();
			const promo_price = $(this).find(".frame-promo-price").val();
			const image = $(this).find(".frame-image").val();
			const description = $(this).find(".frame-description").val();
			const startDate = $(this).find(".frame-start-date").val();
			const endDate = $(this).find(".frame-end-date").val();

			const request = $.ajax({
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

		$(".new-frame").each(function () {
			const product_id = $(this).data("id");
			const new_price = $(this).find(".new-frame-price").val();
			const new_promo_price = $(this).find(".new-frame-promo-price").val();
			const new_image = $(this).find(".new-frame-image").val();
			const new_description = $(this).find(".new-frame-description").val();
			const new_startDate = $(this).find(".new-frame-start-date").val();
			const new_endDate = $(this).find(".new-frame-end-date").val();

			const request = $.ajax({
				url: ajaxurl,
				type: "POST",
				data: {
					action: "add_frame_prices",
					product_id: product_id,
					new_frame_price: new_price,
					new_frame_promo_price: new_promo_price,
					new_frame_image: new_image,
					new_frame_description: new_description,
					new_frame_start_date: new_startDate,
					new_frame_end_date: new_endDate,
				},
			});

			ajaxRequests.push(request);
		});

		$.when.apply($, ajaxRequests).then(function () {
			let allSuccessful = true;
			for (let i = 0; i < arguments.length; i++) {
				const response = arguments[i][0];
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
