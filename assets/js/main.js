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

const slimFrameSelect = new SlimSelect({
	select: "#frame-select",
	settings: {
		allowDeselect: true,
		closeOnSelect: false,
		selectAll: true,
		placeholderText: "избери",
	},
	events: {
		beforeOpen: () => {
			document.getElementById("products-table").classList.add("blurred-unclickable");
		},
		afterClose: () => {
			document.getElementById("chose-frames").submit();
		},
	},
});

const slimFiltersSelect = new SlimSelect({
	select: "#filters-select",
	settings: {
		allowDeselect: true,
		closeOnSelect: true,
		maxValuesShown: 1,
		placeholderText: "Филтри",
	},
	events: {
		afterClose: () => {
			document.getElementById("chose-frames").submit();
		}
	}
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

function selectCategory() {
	const categorySelect = document.getElementById("category-select");
	const framePricesSelect = document.getElementById("frame-prices-select");

	if (categorySelect) {
		const form = categorySelect.closest("form");

		categorySelect.addEventListener("change", () => {
			if (framePricesSelect) {
				framePricesSelect.remove();
			}
			form.submit();
		});
	}
}
selectCategory();

function changePriceVisual() {
	const checkButton = document.getElementById("check-mass-insert");
	const confirmButton = document.getElementById("apply-mass-insert");
	const massSpan = document.getElementById("mass-insert-span");
	const checkProducts = document.getElementsByClassName("check-product");
	const checkAll = document.querySelector(".check-all-products");
	let lastChecked = null;

	for (const checkProduct of checkProducts) {
		checkProduct.addEventListener("click", function (event) {
			if (lastChecked && event.shiftKey) {
				let start = Array.from(checkProducts).indexOf(lastChecked);
				let end = Array.from(checkProducts).indexOf(this);

				let range = [start, end].sort((a, b) => a - b);
				for (let i = range[0]; i <= range[1]; i++) {
					checkProducts[i].checked = lastChecked.checked;
					calculateAllPrices(checkProducts[i]);
				}
			} else {
				calculateAllPrices(this);
			}
			lastChecked = this;
		});
	}

	if (checkAll) {
		checkAll.addEventListener("click", function () {
			Array.from(checkProducts).forEach(function (checkbox) {
				const tr = checkbox.closest("tr");
				const computedStyle = window.getComputedStyle(tr);

				if (computedStyle.display !== "none") {
					checkbox.checked = checkAll.checked;

					calculateAllPrices(checkbox);
				}
			});
		});
	}

	function calculateAllPrices(checkbox) {
		const productId = checkbox.getAttribute("data-product-id");
		const elements = document.querySelectorAll(`[data-product-id='${productId}']`);

		if (elements.length > 1) {
			elements.forEach((element) => {
				if (checkbox.checked) {
					element.dataset.changePrice = "true";
				} else {
					element.dataset.changePrice = "false";
				}
			});

			calculate();
		}
	}

	if (!checkButton || !confirmButton) {
		return;
	}

	massSpan.addEventListener("click", hideConfirmButton);

	checkButton.addEventListener("click", () => {
		const productsTitles = document.querySelectorAll(".product-title");

		for (const productTitle of productsTitles) {
			tr = productTitle.closest("tr");
			tr.style.display = "table-row";
		}

		document.getElementById("search-type").style.display = "none";
		document.getElementById("search-input").value = "";

		calculate(true);
	});

	function calculate(warningMessage = false) {
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
		const checkProducts = document.getElementsByClassName("check-product");
		const noneChecked = Array.from(checkProducts).every(function (checkbox) {
			return !checkbox.checked;
		});
		const searchInput = document.getElementById("search-input");

		if (priceInput.value === "" && promoInput.value === "") {
			if (warningMessage) {
				frame_notifier.warning(`Трябва да има въведена цена или промоция".`);
			}
			errorCount++;
		}

		if (errorCount > 0 || searchInput.value !== "") {
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

		if (noneChecked) {
			hideConfirmButton();
		} else {
			confirmButton.style.display = "inline";
		}
	}

	function hideConfirmButton() {
		confirmButton.style.display = "none";
	}

	function calculateSum(column, operator, sum, round) {
		let oldSum = 0;
		let oldColumnValue;
		let changeFrame = true;
		const iconSpan = column.parentNode.querySelector(".icon");
		if (column.tagName.toLowerCase() === "input") {
			changeFrame = false;
		}
		const oldPriceSpan = document.getElementById(
			`${column.getAttribute("data-type")}-price-result-${column.getAttribute("data-product-id")}`
		);
		const savedSpan = column.querySelector(".saved");

		if (changeFrame) {
			oldColumnValue = parseFloat(savedSpan.innerHTML);
		} else {
			oldColumnValue = parseFloat(column.value);
		}

		const changePrice = column.getAttribute("data-change-price");
		const massPriceSelect = document.getElementById("mass-prices-to-promo");

		if (
			massPriceSelect.value !== "" &&
			(column.classList.contains("frame-table-promo") || column.classList.contains("product-promo-input"))
		) {
			if (massPriceSelect.value === "new-to-promo") {
				oldSum = parseFloat(column.getAttribute("data-saved-price"));
			} else {
				oldSum = parseFloat(column.getAttribute("data-price"));
			}
		} else {
			if (changeFrame) {
				oldSum = parseFloat(savedSpan.innerHTML);
			} else {
				oldSum = parseFloat(column.value);
			}
		}

		if (isNaN(oldSum)) {
			oldSum = 0;
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
				savedSpan.innerHTML = `${oldColumnValue} / <span class="text-success">${result}</span>`;
			} else {
				const selectedFrames = document.getElementById("frame-select");
				const newPriceSpan = ` <span id='${column.getAttribute("data-type")}-price-result-${column.getAttribute(
					"data-product-id"
				)}' class='price-result-span text-success'>${result}</span>`;
				if (oldPriceSpan) {
					oldPriceSpan.remove();
				}

				if (result >= 0 && (!selectedFrames || selectedFrames.value == "")) {
					iconSpan.insertAdjacentHTML("afterend", newPriceSpan);
				}
			}
		} else {
			if (oldPriceSpan) {
				oldPriceSpan.remove();
			}
			column.innerHTML = `<span class="saved">${oldColumnValue}</span>`;
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

		if (storedEditPricesType !== null && storedEditPricesType !== "") {
			$(".price-inputs").removeAttr("readonly");

			if (storedEditPricesType === "now") {
				$(".price-inputs").after("<span class='icon pointer'>⚡</span>");
			} else {
				$(".price-inputs").after("<span class='icon pointer'>💾</span>");
			}
		} else {
			$(".price-inputs").attr("readonly", "readonly");
			$(".price-inputs").after("<span class='icon' style='padding: 0 3px 0 3px;'>🚫</span>");
		}
	}
	getEditPricesType();

	$("#edit-prices-type").on("change", function () {
		const editPricesType = $(this).val();
		sessionStorage.setItem("editPricesType", editPricesType);

		// Remove any existing icons
		$(".price-inputs").next(".icon").remove();

		if (editPricesType !== "") {
			$(".price-inputs").removeAttr("readonly");

			if (editPricesType === "now") {
				$(".price-inputs").after("<span class='icon pointer'>⚡</span>");
			} else {
				$(".price-inputs").after("<span class='icon pointer'>💾</span>");
			}
		} else {
			$(".price-inputs").attr("readonly", "readonly");
			$(".price-inputs").after("<span class='icon' style='padding: 0 3px 0 3px;'>🚫</span>");
		}
	});

	$(document).on("click", ".icon.pointer", function () {
		const element = $(this).prev();
		const productId = element.data("product-id");
		const oldPrice = element.data("value");
		const newPrice = element.val();
		const priceType = element.data("type");
		const editPricesType = sessionStorage.getItem("editPricesType");

		if (editPricesType === "now" && priceType === "sale" && newPrice >= element.data("price")) {
			frame_notifier.warning("Промоционалната цена трябва да бъде по-малка от основната.");
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
		let requestsData = [];
		let error = false;
		const lastProductId = $("#modal-product-id").val();

		console.log("lastId", lastProductId);

		$(".frame-id").each(function () {
			const data = {
				id: $(this).data("id"),
				frame_id: $(this).find(".frame-id").val(),
				frame_price: $(this).find(".frame-price").val(),
				frame_promo_price: $(this).find(".frame-promo-price").val(),
				frame_image: $(this).find(".frame-image").val(),
				frame_description: $(this).find(".frame-description").val(),
				delete_frame: $(this).find(".delete-frame").prop("checked"),
				is_new: false, // Indicate this is not a new frame
			};

			if (data.frame_id === "") {
				frame_notifier.alert("Трябва да изберете Цена №.");
				error = true;
			}
			if (data.frame_image === "") {
				frame_notifier.alert("Трябва да изберете картинка на касата.");
				error = true;
			}
			if (data.frame_description === "") {
				frame_notifier.alert("Трябва да въведете описание.");
				error = true;
			}
			if (data.frame_id !== "-5" && data.frame_price === "") {
				frame_notifier.alert("Трябва да въведете цена.");
				error = true;
			}

			if (error) {
				return false;
			}

			requestsData.push(data);
		});

		$(".new-frame").each(function () {
			const data = {
				product_id: $(this).data("id"),
				frame_id: $(this).find(".frame-id").val(),
				frame_price: $(this).find(".new-frame-price").val(),
				frame_promo_price: $(this).find(".new-frame-promo-price").val(),
				frame_image: $(this).find(".new-frame-image").val(),
				frame_description: $(this).find(".new-frame-description").val(),
				is_new: true, // Indicate this is a new frame
			};

			if (data.frame_id === "") {
				frame_notifier.alert("Трябва да изберете Цена №.");
				error = true;
			}
			if (data.frame_image === "") {
				frame_notifier.alert("Трябва да изберете картинка на касата.");
				error = true;
			}
			if (data.frame_description === "") {
				frame_notifier.alert("Трябва да въведете описание.");
				error = true;
			}
			if (data.frame_id !== "-5" && data.frame_price === "") {
				frame_notifier.alert("Трябва да въведете цена.");
				error = true;
			}

			if (error) {
				return false;
			}

			requestsData.push(data);
		});

		if (error) {
			return;
		}

		$.ajax({
			url: ajaxurl,
			type: "POST",
			data: {
				action: "update_frame_prices",
				frames: requestsData,
			},
			success: function (response) {
				if (response.success) {
					frame_notifier.success("Промените са запазени.");
					sessionStorage.setItem("last_product_id", lastProductId);
					location.reload();
				} else {
					frame_notifier.alert("Промените не са запазени.");
				}
				$("#frameModal").hide();
			},
			error: function () {
				frame_notifier.alert("Промените не са запазени.");
			},
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
		const pricesToPromo = $("#mass-prices-to-promo").val();
		const activeSelect = $("#active-select").val();
		const product_ids = $(".check-product:checked")
			.map(function () {
				return $(this).data("product-id");
			})
			.get();

		if ($("#sum-price-input").val() === "") {
			sum_price = -1;
		}
		if ($("#sum-promotion-input").val() === "") {
			sum_promotion = -1;
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

	$("#order-by-price-icon").click(function () {
		$.ajax({
			url: ajaxurl,
			method: "POST",
			data: {
				action: "order_by_price",
				toggle_order_by_price: true,
			},
			success: function (response) {
				if (response.success) {
					location.reload();
				}
			},
			error: function () {
				frame_notifier.alert(`Грешка.`);
			},
		});
	});

	$("#btn-activate-prices").on("click", function (e) {
		e.preventDefault();

		frame_notifier.confirm(
			`Сигурни ли сте, че искате да замените цените на всички продукти? Това действие е необратимо.`,
			function () {
				$.ajax({
					url: ajaxurl,
					method: "POST",
					data: {
						action: "activate_prices",
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
			},
			function () {
				frame_notifier.info(`Действието е отменено.`);
			}
		);
	});

	$("#btn-activate-frame-prices").on("click", function (e) {
		e.preventDefault();

		frame_notifier.confirm(
			`Сигурни ли сте, че искате да активирате всички неактивни каси? Това действие е необратимо.`,
			function () {
				$.ajax({
					url: ajaxurl,
					method: "POST",
					data: {
						action: "activate_frame_prices",
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
			},
			function () {
				frame_notifier.info(`Действието е отменено.`);
			}
		);
	});
});

function changeFramesButtonColor() {
	const lastProductId = sessionStorage.getItem("last_product_id");
	const framesButton = document.querySelector(`.open-modal[data-id="${lastProductId}"]`);

	if (framesButton) {
		framesButton.classList.remove("btn-primary");
		framesButton.classList.add("btn-success");
	}
}
changeFramesButtonColor();

function searchProducts() {
	const searchInput = document.getElementById("search-input");
	const productsTitles = document.querySelectorAll(".product-title");
	const confirmButton = document.getElementById("apply-mass-insert");
	const searchTypeSelect = document.getElementById("search-type");

	function performSearch() {
		const searchValue = searchInput.value.toLowerCase();
		const searchType = searchTypeSelect.value;

		confirmButton.style.display = "none";

		if (searchInput.value === "") {
			searchTypeSelect.style.display = "none";
		} else {
			searchTypeSelect.style.display = "inline-block";
		}

		productsTitles.forEach(function (productTitle) {
			const titleText = productTitle.textContent.toLowerCase();
			const tr = productTitle.closest("tr");
			let result = false;

			tr.style.display = "none";

			switch (searchType) {
				case "include":
					result = titleText.includes(searchValue);
					break;
				case "starts":
					result = titleText.startsWith(searchValue);
					break;
				case "ends":
					result = titleText.endsWith(searchValue);
					break;
			}

			if (result) {
				tr.style.display = "table-row";
			}
		});
	}

	searchInput.addEventListener("keyup", performSearch);
	searchInput.addEventListener("click", () => {
		searchTypeSelect.style.display = "inline-block";
	});
	searchTypeSelect.addEventListener("change", performSearch);
}
searchProducts();