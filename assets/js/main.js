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
		placeholderText: "избери",
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

			if (elements.length > 1) {
				elements.forEach((element) => {
					if (checkProduct.checked) {
						element.dataset.changePrice = "true";
					} else {
						element.dataset.changePrice = "false";
					}
				});

				calculate();
			}
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
		const productPrices = document.getElementsByClassName("product-price-input");
		const productPromos = document.getElementsByClassName("product-promo-input");
		const tablePrices = document.getElementsByClassName("frame-table-price");
		const tablePromos = document.getElementsByClassName("frame-table-promo");
		const pricesEdit = document.getElementById("mass-edit-prices");
		const pricesRound = document.getElementById("mass-round-prices");

		if (priceInput.value === "" && promoInput.value === "") {
			frame_notifier.warning(`Трябва да има въведена цена или промоция".`);
			errorCount++;
		}

		if (errorCount > 0) {
			hideConfirmButton();
			return;
		}

		for (const tablePrice of tablePrices) {
			calculateSum(tablePrice, operatorPrice, priceInput.value, pricesRound.checked);
		}

		for (const tablePromo of tablePromos) {
			calculateSum(tablePromo, operatorPromo, promoInput.value, pricesRound.checked);
		}

		for (const productPrice of productPrices) {
			calculateSum(productPrice, operatorPrice, priceInput.value, pricesRound.checked);
		}

		for (const productPromo of productPromos) {
			calculateSum(productPromo, operatorPromo, promoInput.value, pricesRound.checked);
		}

		if (pricesEdit.checked) {
			confirmButton.innerText = "Замени текущите цени";
		} else {
			confirmButton.innerText = "Запази цените за по-късно";
		}

		confirmButton.style.display = "inline";
	}

	function hideConfirmButton() {
		confirmButton.style.display = "none";
	}

	function calculateSum(column, operator, sum, round) {
		let oldSum = 0;
		let oldColumnValue;
		let changeFrame = true;
		if (column.tagName.toLowerCase() === "input") {
			changeFrame = false;
		}
		const oldPriceSpan = document.getElementById(
			`${column.getAttribute("data-type")}-price-result-${column.getAttribute("data-product-id")}`
		);

		if (changeFrame) {
			oldColumnValue = parseFloat(column.innerHTML);
		} else {
			oldColumnValue = parseFloat(column.value);
		}

		const changePrice = column.getAttribute("data-change-price");

		if (
			document.getElementById("mass-prices-to-promo").checked &&
			(column.classList.contains("frame-table-promo") || column.classList.contains("product-promo-input"))
		) {
			oldSum = parseFloat(column.getAttribute("data-price"));
		} else {
			if (changeFrame) {
				oldSum = parseFloat(column.innerHTML);
			} else {
				oldSum = parseFloat(column.value);
			}
		}

		const newSum = parseFloat(sum);
		let result = 0;

		if (sum >= 0 && changePrice === "true") {
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

			if (changeFrame && result >= 0) {
				column.innerHTML = `${oldColumnValue} / <span class="text-success">${result}</span>`;
			} else {
				const newPriceSpan = ` <span id='${column.getAttribute("data-type")}-price-result-${column.getAttribute(
					"data-product-id"
				)}' class='price-result-span text-success'>${result}</span>`;
				if (oldPriceSpan) {
					oldPriceSpan.remove();
				}

				if (result >= 0) {
					column.insertAdjacentHTML("afterend", newPriceSpan);
				}
			}
		} else {
			if (oldPriceSpan) {
				oldPriceSpan.remove();
			}
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

	function getEditPricesType() {
		const storedEditPricesType = sessionStorage.getItem("editPricesType");
		$("#edit-prices-type").val(storedEditPricesType);

		if (storedEditPricesType !== "") {
			$(".price-inputs").removeAttr("readonly");
		} else {
			$(".price-inputs").attr("readonly", "readonly");
		}
	}
	getEditPricesType();

	$("#edit-prices-type").on("change", function () {
		const editPricesType = $(this).val();
		sessionStorage.setItem("editPricesType", editPricesType);

		if ($(this).val() !== "") {
			$(".price-inputs").removeAttr("readonly");
		} else {
			$(".price-inputs").attr("readonly", "readonly");
		}
	});

	$(".price-inputs").on("change", function () {
		const element = $(this);
		const productId = element.data("product-id");
		const oldPrice = element.data("value");
		const newPrice = element.val();
		const priceType = element.data("type");
		const editPricesType = sessionStorage.getItem("editPricesType");

		if (editPricesType === "now" && priceType === "sale" && newPrice > element.data("price")) {
			frame_notifier.warning("Промоционалната цена не може да бъде по-висока от основната.");
			return;
		}

		if (
			editPricesType === "later" &&
			priceType === "sale" &&
			newPrice >= parseFloat($("#price-badge-" + productId).text()) &&
			priceType != 0
		) {
			frame_notifier.warning("Промоционалната цена трябва да бъде по-малка от основната.");
			return;
		}

		let badgeId = priceType === "regular" ? "price-badge-" : "price-promo-badge-";
		let badgeClass = "badge bg-warning text-dark";
		let badgeSelector = "#" + badgeId + productId;
		let badge = $(badgeSelector);

		if (badge.length === 0) {
			let badgeContainer = $('<div class="badge-container"></div>');
			badge = $('<span id="' + badgeId + productId + '" class="' + badgeClass + '"></span>');
			badgeContainer.append(badge);
			element.before(badgeContainer);
		}

		$.ajax({
			url: ajaxurl,
			type: "POST",
			data: {
				action: "update_product_price",
				product_id: productId,
				new_price: newPrice,
				price_type: priceType,
				edit_prices_type: editPricesType,
			},
			success: function (response) {
				if (response.success) {
					if (editPricesType === "later") {
						badge.text(newPrice);
						element.val(oldPrice);
					}
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
						<option value=""></option>
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
		const priceEdit = $("#mass-edit-prices").prop("checked");
		const pricesRound = $("#mass-round-prices").prop("checked");
		const pricesToPromo = $("#mass-prices-to-promo").prop("checked");
		const activeSelect = $("#active-select").val();
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
				price_edit: priceEdit,
				prices_round: pricesRound,
				prices_to_promo: pricesToPromo,
				active: activeSelect,
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
				$trElement.find(".new-frame-price, .new-frame-promo-price").hide();
			} else {
				$trElement.find(".new-frame-price, .new-frame-promo-price").show();
			}
		});
	}
	framePricesValidation();
});
