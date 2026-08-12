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
		},
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

function selectCategory() {
	const categorySelect = document.getElementById("category-select");
	const framePricesSelect = document.getElementById("frame-prices-select");

	if (categorySelect) {
		const form = categorySelect.closest("form");

		categorySelect.addEventListener("change", function () {
			if (framePricesSelect) {
				framePricesSelect.remove();
			}

			const formData = new FormData(form);

			for (let [key] of formData.entries()) {
				if (key.startsWith("frame_id[")) {
					formData.delete(key);
				}
			}

			const newForm = document.createElement("form");
			newForm.method = form.method;
			newForm.action = form.action;

			for (let [key, value] of formData.entries()) {
				const input = document.createElement("input");
				input.type = "hidden";
				input.name = key;
				input.value = value;
				newForm.appendChild(input);
			}

			form.parentNode.removeChild(form);

			document.body.appendChild(newForm);
			newForm.submit();
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
		let priceBeforeSale = false;
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

		if (massPriceSelect.value !== "") {
			if (column.classList.contains("frame-table-promo") || column.classList.contains("product-promo-input")) {
				if (massPriceSelect.value === "new-to-promo") {
					oldSum = parseFloat(column.getAttribute("data-saved-price"));
				} else if (massPriceSelect.value === "new-promo-to-promo") {
					oldSum = parseFloat(column.getAttribute("data-saved-promo-price"));
				} else {
					oldSum = parseFloat(column.getAttribute("data-price"));
				}
			} else {
				if (massPriceSelect.value !== "new-to-price") {
					priceBeforeSale = true;
				}

				if (massPriceSelect.value === "new-promo-to-price") {
					oldSum = parseFloat(column.getAttribute("data-saved-promo-price"));
				} else if (massPriceSelect.value === "new-to-price") {
					oldSum = parseFloat(column.getAttribute("data-saved-price"));
				} else {
					oldSum = parseFloat(column.getAttribute("data-promo-price"));
				}
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
					if (priceBeforeSale) {
						result = oldSum / (1 - newSum / 100);
					} else {
						result = (oldSum * (100 + newSum)) / 100;
					}
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

	// Frames Modal
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
					getCopiedFrames();
				} else {
					frame_notifier.warning("Няма добавени цени на каси за този продукт.");
				}
			},
		});
	});

	// Close Frames Modal
	$(".close, .btn-close").on("click", function () {
		$("#frameModal").hide();
	});

	// Variations Modal
	$(".open-variations-modal").on("click", function () {
		const productId = $(this).data("id");

		// Fetch data from the server
		$.ajax({
			url: ajaxurl,
			type: "POST",
			data: {
				action: "fetch_variation_prices",
				product_id: productId,
			},
			success: function (response) {
				if (response.success) {
					$("#variations-modal-body").html(response.data);
					$("#variationsModal").show();

					if (
						sessionStorage.getItem("editPricesType") !== null &&
						sessionStorage.getItem("editPricesType") !== ""
					) {
						$(".price-input").removeAttr("readonly");
						if (sessionStorage.getItem("editPricesType") === "now") {
							$("#save-modal-variation-prices").text("⚡ Промени текущите цени");
							$("#save-modal-variation-prices").attr("data-edit-type", "now");
						} else {
							$("#save-modal-variation-prices").text("💾 Запази цените за по-късно");
							$("#save-modal-variation-prices").attr("data-edit-type", "later");
						}
						$("#save-modal-variation-prices").show();
					} else {
						$(".price-input").attr("readonly", "readonly");
						$("#save-modal-variation-prices").hide();
					}

					massUpdateVariations();
					massVariationToPromoSelect();
				} else {
					frame_notifier.warning("Няма вариации за показване.");
				}
			},
		});
	});

	// Close Variations Modal
	$(".closeVariations, #close-variations").on("click", function () {
		$("#variationsModal").hide();
	});

	// Image Modal
	$(document).on("click", ".open-image-modal", function () {
		const $this = $(this);
		if ($this.hasClass('loading')) return;
		$this.addClass('loading').hide().after('<div class="spinner-border spinner-border-sm ms-2" role="status"><span class="visually-hidden">Loading...</span></div>');

		const productId = $this.data("id");

		// Fetch data from the server
		$.ajax({
			url: ajaxurl,
			type: "POST",
			data: {
				action: "fetch_image_gallery",
				product_id: productId,
			},
			success: function (response) {
				$this.removeClass('loading').show().next('.spinner-border').remove();
				if (response.success) {
					$("#image-modal-body").html(response.data.html);
					$("#imageModal .modal-title").text(response.data.title);
					const editUrl = ajaxurl.replace('admin-ajax.php', 'post.php?post=') + productId + '&action=edit';
					$("#imageModal .modal-footer").prepend('<a href="' + editUrl + '" class="btn btn-primary me-2" target="_blank" onclick="jQuery(\'#imageModal\').hide();">Редактирай продукта</a>');
					$("#imageModal").show();
				} else {
					frame_notifier.warning("Няма изображение за този продукт.");
				}
			},
			error: function () {
				$this.removeClass('loading').show().next('.spinner-border').remove();
				frame_notifier.warning("Грешка при зареждане.");
			},
		});
	});

	// Close Image Modal
	$("#imageModal .close, #imageModal .btn-close").on("click", function () {
		$("#imageModal").hide();
		$("#imageModal .modal-footer .btn-primary").remove();
	});

	$(document).on("click", "#add-new-frame", function () {
		var data = {
			data_id: $(this).data("id"),
		};
		addNewFrame(data);
		initializeDatepickers();
	});

	$(document).on("click", "#copy-frames", function () {
		const pasteButton = document.getElementById("paste-frames");

		sessionStorage.setItem("copyFramesId", $(this).data("id"));
		sessionStorage.setItem("copyFramesName", $(this).data("name"));
		pasteButton.style.display = "none";
		frame_notifier.success(`Касите от ${$(this).data("name")} са копирани. Можете да ги поставите в друг продукт.`);
	});

	$(document).on("click", "#paste-frames", function () {
		const copyId = sessionStorage.getItem("copyFramesId");
		const pasteId = $(this).data("id");

		$.ajax({
			url: ajaxurl,
			method: "POST",
			data: {
				action: "paste_frames",
				copy_id: copyId,
				paste_id: pasteId,
			},
			success: function (response) {
				if (response.success) {
					sessionStorage.setItem("last_product_id", pasteId);
					location.reload();
				}
			},
			error: function () {
				frame_notifier.alert(`Грешка.`);
			},
		});
	});

	function getCopiedFrames() {
		const id = sessionStorage.getItem("copyFramesId");
		const name = sessionStorage.getItem("copyFramesName");
		const pasteButton = document.getElementById("paste-frames");

		if (id && name) {
			pasteButton.innerText = `Постави касите от ${name}`;
			pasteButton.style.display = "inline-block";
		}
	}

	$(document).on("click", ".frame-duplicate", function () {
		var $row = $(this).closest("tr");
		var data = {};

		var data = {
			data_id: $(this).data("id"),
			frame_id: $row.find(".frame-id").val(),
			frame_img: $row.find(".change-frame-image").val(),
			frame_desc: $row.find(".frame-description").val(),
			frame_price: $row.find(".frame-price").val(),
			frame_promo_price: $row.find(".frame-promo-price").val(),
		};

		addNewFrame(data);
		initializeDatepickers();
	});

	// Delete button for existing rows: confirm via notifier + AJAX delete
	$(document).on('click', '.frame-delete-btn', function () {
		const $btn = $(this);
		const $row = $btn.closest('tr');
		const frameId = $row.data('id');

		frame_notifier.confirm(
			'Сигурни ли сте, че искате да изтриете тази каса?',
			function () {
				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: {
						action: 'delete_frame',
						id: frameId,
					},
					success: function (response) {
						if (response.success) {
							frame_notifier.success('Касата е изтрита.');
							$row.remove();
						} else {
							frame_notifier.alert('Грешка при изтриването.');
						}
					},
					error: function () {
						frame_notifier.alert('Грешка при изтриването.');
					},
				});
			},
			function () {
				frame_notifier.info('Действието е отменено.');
			}
		);
	});

	$(document).on("click", ".new-frame-duplicate", function () {
		var $row = $(this).closest("tr");
		var data = {};

		var data = {
			data_id: $(this).data("id"),
			frame_id: $row.find(".frame-id").val(),
			frame_img: $row.find(".change-frame-image").val(),
			frame_desc: $row.find(".new-frame-description").val(),
			frame_price: $row.find(".new-frame-price").val(),
			frame_promo_price: $row.find(".new-frame-promo-price").val(),
		};

		addNewFrame(data);
		initializeDatepickers();
	});

// helper to return copy SVG markup
function copyIconSvg() {
	return `
		<svg class="copy-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
			<rect x="9" y="4" width="11" height="14" rx="2" fill="currentColor" opacity="0.9"/>
			<rect x="4" y="8" width="11" height="14" rx="2" fill="currentColor" opacity="0.6"/>
		</svg>
	`;
}

	$(document).on("click", ".new-frame-delete", function () {
		$(this).closest("tr").remove();
	});

	function addNewFrame(copyData) {
		const newId = new Date().getTime();
		$("#new-frame-table").show();
		$("#new-frame-table tbody").append(`
			<tr class="new-frame" data-id="${copyData.data_id}">
				<td>
					<select class="form-control price-input frame-id">
						<option value=""></option>
						<option value="-5">Основна цена</option>
						${Array.from(
							{ length: 15 },
							(_, i) =>
								`<option value="${i + 1}" ${copyData.frame_id == i + 1 ? "selected" : ""}>${
									i + 1
								}</option>`
						).join("")}
					</select>
				</td>
				<td class="frame-image-container">
					<img id="frame-img-${newId}" class="frame-img" src="${copyData.frame_img ? $("#product-title").data("static-images-path") + copyData.frame_img : ""}">
					<select class="form-control new-frame-image change-frame-image" data-image-id="frame-img-${newId}">
						<option value="">Каса</option>
						${$("#all-frame-images").data("frame-options")}
					</select>
				</td>
				<td><textarea class="form-control new-frame-description" cols="30" rows="3" placeholder="Описание">${
					copyData.frame_desc || ""
				}</textarea></td>
				<td><input type="number" class="form-control price-input new-frame-price" placeholder="Цена" value="${
					copyData.frame_price || ""
				}"></td>
				<td><input type="number" class="form-control price-input new-frame-promo-price" placeholder="Промо" value="${
					copyData.frame_promo_price || ""
				}"></td>
                	<td class="text-center">
                		<div class="new-frame-controls-wrapper">
                			<div class="new-frame-checkbox-container">
                				<input class="form-check-input new-frame-active" type="checkbox" id="new-frame-active-${newId}" checked>
                				<label class="new-frame-active-label" for="new-frame-active-${newId}">Активно</label>
                			</div>
                			<button class="btn btn-link new-frame-duplicate text-primary" data-id="${copyData.data_id}" title="Дублирай" aria-label="Дублирай">${copyIconSvg()}</button>
                			<button class="btn btn-link text-danger new-frame-delete" title="Изтрий" aria-label="Изтрий">❌</button>
                		</div>
                	</td>
			</tr>
    `);

		const newFrameRow = $('[data-image-id="frame-img-' + newId + '"]');
		newFrameRow.val(copyData.frame_img);

		if (newFrameRow.length) {
			newFrameRow[0].scrollIntoView({
				behavior: "smooth",
				block: "start",
			});
		}
	}

	$("#save-modal-prices").on("click", function () {
		let requestsData = [];
		let error = false;
		const lastProductId = $("#modal-product-id").val();

		$(".frame-id").each(function () {
			const data = {
				id: $(this).data("id"),
				frame_id: $(this).find(".frame-id").val(),
				frame_price: $(this).find(".frame-price").val(),
				frame_promo_price: $(this).find(".frame-promo-price").val(),
				frame_image: $(this).find(".frame-image").val(),
				frame_description: $(this).find(".frame-description").val(),
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
				active: $(this).find(".new-frame-active").prop("checked") ? 1 : 0,
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

	$("#save-modal-variation-prices").on("click", function () {
		let requestsData = [];
		let error = false;
		const editType = $(this).attr("data-edit-type");
		const lastProductId = $("#modal-product-id").val();

		$(".variation-row").each(function () {
			const data = {
				variation_id: $(this).data("variation-id"),
				variation_price_input: $("#variation-price-input").val(),
				variation_promo_input: $("#variation-promotion-input").val(),
				variation_price: $(this).find(".variation-price").val(),
				variation_promo_price: $(this).find(".variation-promo-price").val(),
				variation_old_price: $(this).find(".variation-price").data("regular-price"),
				variation_old_promo_price: $(this).find(".variation-promo-price").data("sale-price"),
				variation_price_badge: $(this)
					.find(".variation-price")
					.closest("td")
					.find(".badge-container .badge")
					.text(),
				variation_promo_badge: $(this)
					.find(".variation-promo-price")
					.closest("td")
					.find(".badge-container .badge")
					.text(),
			};

			if (data.variation_price === "") {
				frame_notifier.alert("Трябва да въведете цена.");
				error = true;
			}

			if (error) {
				return false;
			}

			requestsData.push(data);
		});

		$.ajax({
			url: ajaxurl,
			type: "POST",
			data: {
				action: "update_variation_prices",
				edit_type: editType,
				product_id: lastProductId,
				variations: requestsData,
			},
			success: function (response) {
				if (response.success) {
					frame_notifier.success("Промените са запазени.");
					sessionStorage.setItem("last_variation_product_id", lastProductId);
					location.reload();
				} else {
					frame_notifier.alert("Промените не са запазени.");
				}
				$("#variationsModal").hide();
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

		document.getElementById("products-table").classList.add("blurred-unclickable");

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
					sessionStorage.setItem("checked_product_ids", product_ids);
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
				var productIds;
				let startTime = new Date().getTime();
				var processed = 0;
				let lastProcessTime = startTime;
				var $progressDiv = $("#progress-div");
				var $progressBar = $(".progress-bar");
				var $progressBarText = $(".progress-bar-text");

				$.ajax({
					url: ajaxurl,
					method: "POST",
					data: {
						action: "get_product_ids",
					},
					success: function (response) {
						if (response.success) {
							productIds = response.data;
							updatePrices();
						} else {
							frame_notifier.alert(`Грешка при получаването на списъка с продукти.`);
						}
					},
				});

				function updatePrices() {
					if (processed >= productIds.length) {
						frame_notifier.success(`Всички цени са променени.`);
						$progressBar.attr("aria-valuenow", 100).css("width", "100%");
						$progressBarText.text("100%");
						location.reload();
						return;
					}

					$progressDiv.show();

					$.ajax({
						url: ajaxurl,
						method: "POST",
						data: {
							action: "activate_single_price",
							product_id: productIds[processed],
						},
						success: function (response) {
							if (response.success) {
								processed++;
								var percentComplete = Math.round((processed / productIds.length) * 100);
								$progressBar.attr("aria-valuenow", percentComplete).css("width", percentComplete + "%");
								$progressBarText.html(
									`<strong>${percentComplete}%</strong><br>${response.data["product_title"]}`
								);
								document.title = `${percentComplete}% ${window.location.hostname}`;

								// Calculate time metrics
								let currentTime = new Date().getTime();
								let timeSpent = currentTime - startTime;
								let averageTimePerItem = timeSpent / processed;
								let remainingItems = productIds.length - processed;
								let remainingTime = averageTimePerItem * remainingItems;

								let seconds = Math.floor((remainingTime / 1000) % 60);
								let minutes = Math.floor((remainingTime / (1000 * 60)) % 60);

								if (processed > 1) {
									$progressBarText.append(`<br><i>Оставащо време: ${minutes}мин и ${seconds}сек</i>`);
								} else {
									$progressBarText.append(`<br>Оставащо време: изчисляване...`);
								}

								lastProcessTime = currentTime;

								updatePrices();
							} else {
								frame_notifier.alert(
									`Грешка при промяна на цената за продукт ${productIds[processed]}.`
								);
							}
						},
						error: function (xhr, status, error) {
							frame_notifier.alert(`Грешка при промяна на цената. Моля опитайте отново.`);
						},
					});
				}
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

	function changeProductStatus() {
		const changeStatusSelects = document.querySelectorAll(".product-status");

		changeStatusSelects.forEach((select) => {
			select.addEventListener("change", function () {
				const productId = select.getAttribute("data-product-id");
				const status = select.value;

				$.ajax({
					url: ajaxurl,
					type: "POST",
					data: {
						action: "change_product_status",
						product_id: productId,
						status: status,
					},
					success: function (response) {
						if (response.success) {
							frame_notifier.success(`Статусът на продукта е променен.`);
						} else {
							frame_notifier.alert(`Статусът на продукта не е променен.`);
						}
					},
				});
			});
		});
	}
	changeProductStatus();
});

function changeFramesButtonColor() {
	const lastProductId = sessionStorage.getItem("last_product_id");
	const framesButton = document.querySelector(`.open-modal[data-id="${lastProductId}"]`);
	const allFramesButtons = document.querySelectorAll(".open-modal");

	if (framesButton) {
		framesButton.classList.remove("btn-primary");
		framesButton.classList.add("btn-success");
	}

	allFramesButtons.forEach((element) => {
		element.addEventListener("click", function () {
			allFramesButtons.forEach((btn) => {
				btn.classList.remove("btn-warning");
			});

			this.classList.add("btn-warning");
		});
	});
}
changeFramesButtonColor();

function changeVariationsButtonColor() {
	const lastProductId = sessionStorage.getItem("last_variation_product_id");
	const variationsButton = document.querySelectorAll(`.open-variations-modal[data-id="${lastProductId}"]`);
	const allVariationsButtons = document.querySelectorAll(".open-variations-modal");

	if (variationsButton) {
		variationsButton.forEach((element) => {
			element.classList.add("bg-success", "text-white");
		});
	}

	allVariationsButtons.forEach((element) => {
		element.addEventListener("click", function () {
			allVariationsButtons.forEach((btn) => {
				btn.classList.remove("bg-warning");
			});

			const tr = this.closest("tr");
			const twinButtons = tr.querySelectorAll(".open-variations-modal");

			twinButtons.forEach((btn) => {
				btn.classList.add("bg-warning");
			});
		});
	});
}
changeVariationsButtonColor();

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
			const checkbox = productTitle.closest("tr").querySelector("input[type=checkbox]");
			const changePriceElements = tr.querySelectorAll("[data-change-price]");
			let result = false;

			tr.style.display = "none";

			if (checkbox) {
				checkbox.checked = false;
				changePriceElements.forEach((element) => {
					element.setAttribute("data-change-price", false);
				});
			}

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

				if (checkbox) {
					checkbox.checked = true;
					changePriceElements.forEach((element) => {
						element.setAttribute("data-change-price", true);
					});
				}
			}
		});
	}

	if (searchInput) {
		searchInput.addEventListener("keyup", performSearch);
		searchInput.addEventListener("click", () => {
			searchTypeSelect.style.display = "inline-block";
		});
		searchTypeSelect.addEventListener("change", performSearch);
	}
}
searchProducts();

function priceToPromoSelect() {
	const sumPriceInput = document.getElementById("sum-price-input");
	const sumPromotionInput = document.getElementById("sum-promotion-input");
	const massPricesToPromoContainer = document.getElementById("mass-prices-to-promo-container");
	const massPricesToPromoSelect = document.getElementById("mass-prices-to-promo");

	// Define all possible options with original values
	const allOptions = [
		{
			value: "old-to-promo",
			text: "Цена към промо",
			title: "Промоционалната цена се изчислява според текущата цена на продукта.",
		},
		{
			value: "new-to-promo",
			text: "Запазена цена към промо",
			title: "Промоционалната цена се изчислява според запазената за по-късно цена на продукта.",
		},
		{
			value: "new-promo-to-promo",
			text: "Запазено промо към промо",
			title: "Промоционалната цена се изчислява според запазената за по-късно промоционална цена на продукта.",
		},
		{
			value: "old-promo-to-price",
			text: "Промо към цена",
			title: "Базовата цена се изчислява според текущата промоционална цена на продукта.",
		},
		{
			value: "new-promo-to-price",
			text: "Запазено промо към цена",
			title: "Базовата цена се изчислява според запазената за по-късно промоционална цена на продукта.",
		},
		{
			value: "new-to-price",
			text: "Запазена цена към цена",
			title: "Базовата цена се изчислява според запазената за по-късно цена на продукта.",
		},
	];

	function showHidePriceToPromoSelect() {
		if (!sumPriceInput || !sumPromotionInput) {
			return;
		}

		const priceFilled = sumPriceInput.value !== "";
		const promoFilled = sumPromotionInput.value !== "";

		if (priceFilled || promoFilled) {
			if (priceFilled && promoFilled) {
				massPricesToPromoContainer.style.display = "none";
				massPricesToPromoSelect.innerHTML = "<option value=''></option>";
				return;
			}

			massPricesToPromoContainer.style.display = "inline-block";
			updateSelectOptions(
				priceFilled
					? ["old-to-promo", "new-to-promo", "new-promo-to-promo"]
					: ["old-promo-to-price", "new-promo-to-price", "new-to-price"]
			);
		} else {
			massPricesToPromoContainer.style.display = "none";
			massPricesToPromoSelect.innerHTML = "<option value=''></option>";
		}
	}

	function updateSelectOptions(removeOptions) {
		massPricesToPromoSelect.innerHTML = "<option value=''></option>";

		allOptions.forEach((option) => {
			if (!removeOptions.includes(option.value)) {
				const opt = document.createElement("option");
				opt.value = option.value;
				opt.textContent = option.text;
				opt.title = option.title;
				massPricesToPromoSelect.appendChild(opt);
			}
		});
	}

	if (sumPriceInput && sumPromotionInput) {
		sumPriceInput.addEventListener("keyup", showHidePriceToPromoSelect);
		sumPriceInput.addEventListener("paste", showHidePriceToPromoSelect);
		sumPromotionInput.addEventListener("keyup", showHidePriceToPromoSelect);
		sumPromotionInput.addEventListener("paste", showHidePriceToPromoSelect);
	}

	showHidePriceToPromoSelect();
}

priceToPromoSelect();

async function fetchGitHubRelease() {
	const urlParams = new URLSearchParams(window.location.search);
	const hasPromotionsPage = urlParams.get("page") === "frames-list-page";

	if (!hasPromotionsPage) {
		return;
	}

	const response = await fetch("https://api.github.com/repos/iztokinvest/doors_frames/releases/latest");
	const currentVersion = document.getElementById("extension-version");
	const wpBody = document.getElementById("wpbody-content");

	const data = await response.json();

	if (data.tag_name && currentVersion && data.tag_name != currentVersion.innerHTML) {
		wpBody.insertAdjacentHTML(
			"afterbegin",
			`<div class="alert alert-warning alert-dismissible fade show" role="alert">
				Налична е нова версия на разширението: <strong>${data.tag_name}</strong>. В момента използвате <strong>${currentVersion.innerHTML}</strong>. <a href="?update_frames=1">Обновете от тук!</a>
			<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
			</div>`
		);
	}
}
fetchGitHubRelease();

function getLastCheckedProducts() {
	const checkProducts = sessionStorage.getItem("checked_product_ids");

	if (checkProducts) {
		const buttonHTML =
			"<button class='btn btn-sm btn-success' id='last-checked-products'>Възстанови маркираните!</button>";
		const infoArea = frame_notifier.info(buttonHTML, buttonHTML);

		document.getElementById("last-checked-products").addEventListener("click", () => {
			const productIds = checkProducts.split(",").map((id) => id.trim());
			const checkboxes = document.querySelectorAll("input.check-product");
			const changePriceElements = document.querySelectorAll("[data-change-price]");
			const checkAll = document.querySelector(".check-all-products");
			let countChecked = 0;

			checkboxes.forEach((checkbox) => {
				const checkboxId = checkbox.getAttribute("data-product-id");
				if (productIds.includes(checkboxId)) {
					checkbox.checked = true;
					countChecked++;
				}
			});

			if (countChecked === checkboxes.length) {
				checkAll.checked = true;
			}

			changePriceElements.forEach((changePriceElement) => {
				const priceId = changePriceElement.getAttribute("data-product-id");

				if (productIds.includes(priceId)) {
					changePriceElement.setAttribute("data-change-price", true);
				}
			});
		});

		sessionStorage.removeItem("checked_product_ids");

		return infoArea;
	}
}
getLastCheckedProducts();

function massUpdateVariations() {
	const updateButton = document.getElementById("variation-mass-prices");
	const checklists = document.querySelectorAll(".variation-row input[type=checkbox]");

	checklists.forEach((element) => {
		element.addEventListener("change", updateVariationPrices);
	});

	if (updateButton) {
		updateButton.addEventListener("click", updateVariationPrices);
	}

	function updateVariationPrices() {
		const priceOperator = document.getElementById("variation-operator-price-select");
		const priceinput = document.getElementById("variation-price-input");
		const promoOperator = document.getElementById("variation-operator-promotion-select");
		const promoinput = document.getElementById("variation-promotion-input");
		const roundPrices = document.getElementById("variation-mass-round-prices");
		const toPromo = document.getElementById("variation-prices-to-promo");
		const savedPriceInputs = document.getElementsByClassName("variation-price");
		const savedPromoInputs = document.getElementsByClassName("variation-promo-price");

		if (priceinput.value != "") {
			for (const price of savedPriceInputs) {
				const closestCheckbox = price.closest("tr").querySelector("input[type=checkbox]");

				let basePrice;
				if (toPromo.value == "promo-to-price") {
					basePrice = price.getAttribute("data-sale-price");
				} else if (toPromo.value == "new-promo-to-price") {
					basePrice = price.getAttribute("data-saved-sale-price");
				} else if (toPromo.value == "new-price-to-price") {
					basePrice = price.getAttribute("data-saved-regular-price");
				} else {
					basePrice = price.getAttribute("data-regular-price");
				}

				price.value = calculateVariation(
					closestCheckbox.checked,
					priceOperator.value,
					basePrice,
					priceinput.value,
					roundPrices.checked,
					toPromo.value == "promo-to-price" || toPromo.value == "new-promo-to-price" ? true : false
				);
			}
		}

		if (promoinput.value != "") {
			for (const promo of savedPromoInputs) {
				const closestCheckbox = promo.closest("tr").querySelector("input[type=checkbox]");
				let basePrice;
				if (toPromo.value == "price-to-promo") {
					basePrice = promo.getAttribute("data-regular-price");
				} else if (toPromo.value == "new-price-to-promo") {
					basePrice = promo.getAttribute("data-saved-regular-price");
				} else if (toPromo.value == "new-promo-to-promo") {
					basePrice = promo.getAttribute("data-saved-sale-price");
				} else {
					basePrice = promo.getAttribute("data-sale-price");
				}
				promo.value = calculateVariation(
					closestCheckbox.checked,
					promoOperator.value,
					basePrice,
					promoinput.value,
					roundPrices.checked
				);
			}
		}
	}

	function calculateVariation(checked, operator, oldSumValue, newSumValue, round, priceBeforeSale = false) {
		let oldSum = parseFloat(oldSumValue);
		let newSum = parseFloat(newSumValue);

		if (isNaN(oldSum)) {
			oldSum = 0;
		}

		if (!checked) {
			return oldSum;
		}

		switch (operator) {
			case "+":
				result = oldSum + newSum;
				break;
			case "-":
				result = oldSum - newSum;
				break;
			case "+%":
				if (priceBeforeSale) {
					result = oldSum / (1 - newSum / 100);
				} else {
					result = (oldSum * (100 + newSum)) / 100;
				}
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

		return result;
	}
}

function massVariationToPromoSelect() {
	const priceInput = document.getElementById("variation-price-input");
	const promoInput = document.getElementById("variation-promotion-input");
	const toPromoSelect = document.getElementById("variation-prices-to-promo");
	const container = document.getElementById("mass-variation-prices-to-promo-container");

	// Function to update select options and container visibility
	function updateSelectOptions() {
		// Check if inputs are empty
		const isPriceEmpty = !priceInput.value.trim();
		const isPromoEmpty = !promoInput.value.trim();

		// Reset select options
		toPromoSelect.innerHTML = '<option value=""></option>';

		if ((!isPriceEmpty && isPromoEmpty) || (isPriceEmpty && !isPromoEmpty)) {
			// Show container
			container.style.display = "inline-block";

			// Add options based on which input is not empty
			if (!isPriceEmpty && isPromoEmpty) {
				// Only price input is not empty
				toPromoSelect.innerHTML += `
                    <option value="promo-to-price" title="Базовата цена се изчислява според текущата промоционална цена на продукта.">Промо към цена</option>
                    <option value="new-promo-to-price" title="Базовата цена се изчислява според запазената за по-късно промоционална цена на продукта.">Запазено промо към цена</option>
					<option value="new-price-to-price" title="Базовата цена се изчислява според запазената за по-късно базова цена на продукта.">Запазена цена към цена</option>
                `;
			} else if (isPriceEmpty && !isPromoEmpty) {
				// Only promo input is not empty
				toPromoSelect.innerHTML += `
                    <option value="price-to-promo" title="Базовата цена се изчислява според запазената за по-късно промоционална цена на продукта.">Цена към промо</option>
                    <option value="new-price-to-promo" title="Промоционалната цена се изчислява според запазената за по-късно цена на продукта.">Запазена цена към промо</option>
					<option value="new-promo-to-promo" title="Промоционалната цена се изчислява според запазената за по-късно промоционална цена на продукта.">Запазено промо към промо</option>
                `;
			}
		} else {
			// Hide container when both are empty or both are not empty
			container.style.display = "none";
		}
	}

	// Add event listeners for input changes
	priceInput.addEventListener("input", updateSelectOptions);
	promoInput.addEventListener("input", updateSelectOptions);

	// Initial call to set up the select options
	updateSelectOptions();
}
