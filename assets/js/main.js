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

const frame_notifier = new AWN({
	durations: {
		global: 5000,
		position: "bottom-right",
	},
});

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

new SlimSelect({
	select: "#frame-select",
	settings: {
		allowDeselect: true,
		closeOnSelect: false,
		selectAll: true,
		placeholderText: "Цена №",
	},
});

function editTab() {
	const editTabButton = document.getElementById("edit-tab");
	const tabBox = document.getElementById("tab-box");

	if (editTabButton) {
		editTabButton.addEventListener("click", () => {
			editTabButton.style.display = "none";
			tabBox.style.display = "flex";
		});
	}
}
editTab();

function changePriceVisual() {
	const checkButton = document.getElementById("check-mass-insert");
	const confirmButton = document.getElementById("apply-mass-insert");
	const massSpan = document.getElementById("mass-insert-span");
	const checkProducts = document.getElementsByClassName("check-product");

	for (const checkProduct of checkProducts) {
		checkProduct.addEventListener("click", () => {
			const productId = checkProduct.getAttribute("data-product-id");
			const elements = document.querySelectorAll(`[data-product-id='${productId}']`);

			elements.forEach((element) => {
				if (checkProduct.checked) {
					element.dataset.changePrice = "true";
				} else {
					element.dataset.changePrice = "false";
				}
			});

			calculate();
		});
	}

	if (!checkButton || !confirmButton) {
		return;
	}

	massSpan.addEventListener("click", hideConfirmButton);

	checkButton.addEventListener("click", calculate);

	function calculate() {
		let errorCount = 0;
		const operatorPrice = document.getElementById("operator-price-select").value;
		const operatorPromo = document.getElementById("operator-promotion-select").value;
		const priceInput = document.getElementById("sum-price-input");
		const promoInput = document.getElementById("sum-promotion-input");
		const tablePrices = document.getElementsByClassName("frame-table-price");
		const tablePromos = document.getElementsByClassName("frame-table-promo");
		const startDate = document.getElementById("mass-start-date");
		const endDate = document.getElementById("mass-end-date");
		const pricesEdit = document.getElementById("mass-edit-prices");
		const pricesRound = document.getElementById("mass-round-prices");
		const today = new Date();
		today.setHours(0, 0, 0, 0);

		if ((!startDate.value || !endDate.value) && !pricesEdit.checked) {
			frame_notifier.warning(`Трябва да бъдат избрани дати или да е маркирана отметката "Редактирай цените".`);
			errorCount++;
		}

		if (priceInput.value === "" && promoInput.value === "") {
			frame_notifier.warning(`Трябва да има въведена цена или промоция".`);
			errorCount++;
		}

		if (errorCount > 0) {
			hideConfirmButton();
			return;
		}

		for (const tablePrice of tablePrices) {
			const rowPriceEndDate = tablePrice.getAttribute("data-end-date");
			let reset = false;
			if (pricesEdit.checked && new Date(rowPriceEndDate) < today) {
				reset = true;
			}
			calculateSum(tablePrice, operatorPrice, priceInput.value, pricesRound.checked, reset);
		}

		for (const tablePromo of tablePromos) {
			const rowPromoEndDate = tablePromo.getAttribute("data-end-date");
			let reset = false;
			if (pricesEdit.checked && new Date(rowPromoEndDate) < today) {
				reset = true;
			}
			calculateSum(tablePromo, operatorPromo, promoInput.value, pricesRound.checked, reset);
		}

		confirmButton.style.display = "inline";
	}

	function hideConfirmButton() {
		confirmButton.style.display = "none";
	}

	function calculateSum(column, operator, sum, round, reset) {
		let oldSum = 0;
		const oldColumnValue = parseInt(column.innerHTML);
		const changePrice = column.getAttribute("data-change-price");

		if (document.getElementById("mass-prices-to-promo").checked && column.classList.contains("frame-table-promo")) {
			oldSum = parseInt(column.getAttribute("data-price"));
		} else {
			oldSum = parseInt(column.innerHTML);
		}

		const newSum = parseInt(sum);
		let result = 0;

		if (sum > 0 && changePrice === "true") {
			switch (operator) {
				case "+":
					result = oldSum + newSum;
					break;
				case "-":
					result = oldSum - newSum;
					break;
				case "+%":
					result = (oldSum * (100 + newSum)) / 100;
					break;
				case "-%":
					result = (oldSum * (100 - newSum)) / 100;
					break;
				case "=":
					result = newSum;
					break;
				default:
					break;
			}

			if (round) {
				result = result % 1 >= 0.5 ? Math.ceil(result) : Math.floor(result);
			}

			if (reset) {
				column.innerHTML = oldColumnValue;
				return;
			}

			column.innerHTML = `${oldColumnValue} / <span class="text-success">${result}</span>`;
		} else {
			column.innerHTML = oldColumnValue;
		}
	}
}
changePriceVisual();

jQuery(document).ready(function ($) {
	$("#tab-button").on("click", function () {
		const categoryId = $("#tab-title").data("category-id");
		const tabText = $("#tab-title").val();
		const tableText = $("#table-text").val();

		if (tabText === "") {
			frame_notifier.warning(`Трябва да въведете име на таба.`);
			return;
		}

		$.ajax({
			url: ajaxurl,
			type: "POST",
			data: {
				action: "update_tab",
				category_id: categoryId,
				tab_text: tabText,
				table_text: tableText,
			},
			success: function (response) {
				if (response.success) {
					const editTab = document.getElementById("edit-tab");
					const spanTab = editTab.querySelector("span");
					frame_notifier.success(`Текстът е променен.`);
					spanTab.innerText = tabText;
					spanTab.classList.remove("bg-danger");
					spanTab.classList.add("bg-warning", "text-dark");
					editTab.style.display = "inline";
					document.getElementById("tab-box").style.display = "none";
				} else {
					frame_notifier.alert(`Текстът не променен.`);
				}
			},
		});
	});

	$(".price-inputs").on("change", function () {
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
		const newId = new Date().getTime();
		$("#new-frame-table").show();
		$("#new-frame-table tbody").append(`
			<tr class="new-frame" data-id="${$(this).data("id")}">>
				<td>
					<select class="form-control price-input frame-id">
						<option value="">Цена №</option>
						<option value="-5">Основна цена</option>
						${Array.from({ length: 15 }, (_, i) => `<option value="${i + 1}">${i + 1}</option>`).join("")}
					</select>
				</td>
				<td class="frame-image-container">
					<img id="frame-img-${newId}" class="frame-img">
					<select class="form-control new-frame-image change-frame-image" data-image-id="frame-img-${newId}">
						<option value="">Каса</option>
						${$("#all-frame-images").data("frame-options")}
					</select>
				</td>
				<td><textarea class="form-control new-frame-description" cols="30" rows="3" placeholder="Описание"></textarea></td>
				<td><input type="number" step="0.01" class="form-control price-input new-frame-price" placeholder="Цена"></td>
				<td><input type="number" step="0.01" class="form-control price-input new-frame-promo-price" placeholder="Промо"></td>
				<td><input required type="text" class="form-control datepicker-input new-frame-start-date" placeholder="Начална дата" /></td>
				<td><input required type="text" class="form-control datepicker-input new-frame-end-date" placeholder="Крайна дата" /></td>
			</tr>
    `);
		initializeDatepickers();
	});

	$("#save-modal-prices").on("click", function () {
		let requestTrue = [];
		let requests = [];
		let error = false;

		$(".frame-id").each(function () {
			const id = $(this).data("id");
			const frame_id = $(this).find(".frame-id").val();
			const price = $(this).find(".frame-price").val();
			const promo_price = $(this).find(".frame-promo-price").val();
			const image = $(this).find(".frame-image").val();
			const description = $(this).find(".frame-description").val();
			const startDate = $(this).find(".frame-start-date").val();
			const endDate = $(this).find(".frame-end-date").val();
			const delete_frame = $(this).find(".delete-frame").prop("checked");

			if (frame_id === "") {
				frame_notifier.alert("Трябва да изберете Цена №.");
				error = true;
			}
			if (image === "") {
				frame_notifier.alert("Трябва да изберете картинка на касата.");
				error = true;
			}
			if (description === "") {
				frame_notifier.alert("Трябва да въведете описание.");
				error = true;
			}
			if (frame_id !== "-5") {
				if (price === "") {
					frame_notifier.alert("Трябва да въведете цена.");
					error = true;
				}
				if (startDate === "") {
					frame_notifier.alert("Трябва да въведете начална дата.");
					error = true;
				}
				if (endDate === "") {
					frame_notifier.alert("Трябва да въведете крайна дата.");
					error = true;
				}
			}

			if (error) {
				return;
			}

			const request = $.ajax({
				url: ajaxurl,
				type: "POST",
				data: {
					action: "update_frame_prices",
					id: id,
					frame_id: frame_id,
					frame_price: price,
					frame_promo_price: promo_price,
					frame_image: image,
					frame_description: description,
					frame_start_date: startDate,
					frame_end_date: endDate,
					delete_frame: delete_frame,
				},
			});

			request.done(function (response) {
				requestTrue.push(true);
			});

			requests.push(request);
		});

		$(".new-frame").each(function () {
			const product_id = $(this).data("id");
			const frame_id = $(this).find(".frame-id").val();
			const new_price = $(this).find(".new-frame-price").val();
			const new_promo_price = $(this).find(".new-frame-promo-price").val();
			const new_image = $(this).find(".new-frame-image").val();
			const new_description = $(this).find(".new-frame-description").val();
			const new_startDate = $(this).find(".new-frame-start-date").val();
			const new_endDate = $(this).find(".new-frame-end-date").val();

			if (frame_id === "") {
				frame_notifier.alert("Трябва да изберете Цена №.");
				error = true;
			}
			if (new_image === "") {
				frame_notifier.alert("Трябва да изберете картинка на касата.");
				error = true;
			}
			if (new_description === "") {
				frame_notifier.alert("Трябва да въведете описание.");
				error = true;
			}
			if (frame_id !== "-5") {
				if (new_price === "") {
					frame_notifier.alert("Трябва да въведете цена.");
					error = true;
				}
				if (new_startDate === "") {
					frame_notifier.alert("Трябва да въведете начална дата.");
					error = true;
				}
				if (new_endDate === "") {
					frame_notifier.alert("Трябва да въведете крайна дата.");
					error = true;
				}
			}

			if (error) {
				return;
			}

			const request = $.ajax({
				url: ajaxurl,
				type: "POST",
				data: {
					action: "add_frame_prices",
					product_id: product_id,
					frame_id: frame_id,
					new_frame_price: new_price,
					new_frame_promo_price: new_promo_price,
					new_frame_image: new_image,
					new_frame_description: new_description,
					new_frame_start_date: new_startDate,
					new_frame_end_date: new_endDate,
				},
			});

			request.done(function (response) {
				requestTrue.push(true);
			});

			requests.push(request);
		});

		$.when.apply($, requests).done(function () {
			if (error) {
				return;
			}
			if (requestTrue.length > 0) {
				frame_notifier.success("Промените са запазени.");
				location.reload();
			} else {
				frame_notifier.alert("Промените не са запазени.");
			}

			$("#frameModal").hide();
		});
	});

	$("#apply-mass-insert").on("click", function () {
		const frameIds = $("#frame-select").val();
		const operator_price = $("#operator-price-select").val();
		let sum_price = parseFloat($("#sum-price-input").val());
		const operator_promotion = $("#operator-promotion-select").val();
		let sum_promotion = parseFloat($("#sum-promotion-input").val());
		const startDate = $("#mass-start-date").val();
		const endDate = $("#mass-end-date").val();
		const pricesRound = $("#mass-round-prices").prop("checked");
		const pricesToPromo = $("#mass-prices-to-promo").prop("checked");
		const product_ids = $(".check-product:checked")
			.map(function () {
				return $(this).data("product-id");
			})
			.get();

		if (isNaN(sum_price)) {
			sum_price = 0;
		}
		if (isNaN(sum_promotion)) {
			sum_promotion = 0;
		}

		$.ajax({
			url: ajaxurl,
			method: "POST",
			data: {
				action: "mass_insert_frames",
				frame_ids: frameIds,
				product_ids: product_ids,
				operator_price: operator_price,
				sum_price: sum_price,
				operator_promotion: operator_promotion,
				sum_promotion: sum_promotion,
				start_date: startDate,
				end_date: endDate,
				prices_round: pricesRound,
				prices_to_promo: pricesToPromo,
			},
			success: function (response) {
				if (response.success) {
					frame_notifier.success(`Цените са променени.`);
					location.reload();
				} else {
					frame_notifier.alert(`Цените не са променени.`);
				}
			},
			error: function () {
				frame_notifier.alert(`Цените не са променени.`);
			},
		});
	});

	function showHideMassDates() {
		if ($("#mass-edit-prices").prop("checked")) {
			$("#mass-dates").hide();
			$("#mass-start-date, #mass-end-date").val("");
		} else {
			$("#mass-dates").show();
		}
	}
	$("#mass-edit-prices").on("change", showHideMassDates);
	showHideMassDates();

	function changeFrameImages() {
		$(document).on("change", ".change-frame-image", function () {
			const $selectElement = $(this);
			const selectedImage = $selectElement.val();
			const imgId = $selectElement.data("image-id");
			const $imgElement = $("#" + imgId);
			const staticPath = $("#product-title").data("static-images-path");

			$imgElement.attr("src", staticPath + selectedImage);
		});
	}
	changeFrameImages();

	function framePricesValidation() {
		$(document).on("change", ".frame-id", function () {
			const $trElement = $(this).closest("tr");

			if ($(this).val() === "-5") {
				$trElement
					.find(".new-frame-price, .new-frame-promo-price, .new-frame-start-date, .new-frame-end-date")
					.hide();
			} else {
				$trElement
					.find(".new-frame-price, .new-frame-promo-price, .new-frame-start-date, .new-frame-end-date")
					.show();
			}
		});
	}
	framePricesValidation();
});
