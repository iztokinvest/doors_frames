(function () {
	'use strict';
	const pickers = [];

	document.querySelectorAll('[data-history-filter]').forEach(function (select) {
		const isProduct = select.dataset.historyFilter === 'product';
		const placeholder = isProduct ? 'Всички артикули' : 'Всички автори';
		let timer;
		let controller;
		let requestId = 0;
		let waiting = [];
		let picker;

		picker = new SlimSelect({
			select: select,
			settings: {
				allowDeselect: true,
				showSearch: true,
				placeholderText: placeholder,
				searchPlaceholder: 'Напишете име или ID…',
				searchText: 'Напишете име или ID за търсене.',
				searchingText: 'Търсене…',
			},
			events: {
				search: function (query) {
					clearTimeout(timer);
					if (controller) controller.abort();
					const currentRequest = ++requestId;
					query = query.trim();
					if (picker) {
						picker.settings.searchText = query === ''
							? 'Напишете име или ID за търсене.' : 'Няма съвпадения в хронологията.';
					}
					if (query === '') {
						const completed = waiting;
						waiting = [];
						completed.forEach(function (promise) { promise.resolve([]); });
						return [];
					}
					return new Promise(function (resolve, reject) {
						waiting.push({ resolve: resolve, reject: reject });
						timer = setTimeout(async function () {
							controller = new AbortController();
							const url = new URL(doorsFramesHistoryFilters.url, window.location.href);
							url.search = new URLSearchParams({
								action: 'doors_frames_history_search_filters',
								nonce: doorsFramesHistoryFilters.nonce,
								filter_type: select.dataset.historyFilter,
								search: query,
							}).toString();
							try {
								const response = await fetch(url, { credentials: 'same-origin', signal: controller.signal });
								const result = await response.json();
								if (currentRequest !== requestId) return;
								if (!response.ok || !result.success || !Array.isArray(result.data)) {
									throw new Error('Търсенето не е успешно. Опитайте отново.');
								}
								const completed = waiting;
								waiting = [];
								completed.forEach(function (promise) { promise.resolve(result.data); });
							} catch (error) {
								if (currentRequest !== requestId) return;
								const completed = waiting;
								waiting = [];
								completed.forEach(function (promise) { promise.reject(new Error('Търсенето не е успешно. Опитайте отново.')); });
							}
							}, 250);
					});
				},
			},
		});
		pickers.push(picker);
	});

	const form = document.querySelector('.doors-frames-history-filters');
	const tableElement = document.getElementById('doors-frames-history-table');
	if (!form || !tableElement) return;
	const errorNotice = document.getElementById('history-table-error');
	const operationNotice = document.getElementById('history-operation-filter');
	let tableRequest;
	let clearing = false;
	const table = new DataTable(tableElement, {
		serverSide: true,
		processing: true,
		pageLength: 50,
		lengthMenu: [25, 50, 100],
		searchDelay: 400,
		order: [[0, 'desc']],
		language: {
			processing: 'Зареждане…',
			loadingRecords: 'Зареждане…',
			search: 'Търсене:',
			searchPlaceholder: 'Име, ID или цена…',
			lengthMenu: 'Показвай _MENU_ реда',
			info: '_START_–_END_ от _TOTAL_ промени',
			infoEmpty: 'Няма записани промени',
			infoFiltered: '(от общо _MAX_)',
			zeroRecords: 'Няма промени за избраните филтри.',
			emptyTable: 'Няма записани промени.',
			paginate: { first: 'Първа', previous: 'Предишна', next: 'Следваща', last: 'Последна' },
			aria: { orderable: 'Сортирай по тази колона', orderableReverse: 'Обърни сортирането', orderableRemove: 'Премахни сортирането' },
		},
		ajax: async function (request, callback) {
			if (tableRequest) tableRequest.abort();
			const controller = new AbortController();
			tableRequest = controller;
			errorNotice.hidden = true;
			const params = new URLSearchParams(new FormData(form));
			params.set('action', 'doors_frames_history_load_table');
			params.set('nonce', doorsFramesHistoryFilters.tableNonce);
			params.set('draw', request.draw);
			params.set('start', request.start);
			params.set('length', request.length);
			params.set('search[value]', request.search.value);
			if (request.order.length) {
				params.set('order[0][column]', request.order[0].column);
				params.set('order[0][dir]', request.order[0].dir);
			}
			const url = new URL(doorsFramesHistoryFilters.url, window.location.href);
			url.search = params.toString();
			try {
				const response = await fetch(url, { credentials: 'same-origin', signal: controller.signal });
				const result = await response.json();
				if (!response.ok || !Array.isArray(result.data)) throw new Error('Table request failed');
				if (controller.signal.aborted) return;
				errorNotice.hidden = !result.error;
				callback({ draw: result.draw, recordsTotal: result.recordsTotal, recordsFiltered: result.recordsFiltered, data: result.data });
			} catch (error) {
				if (controller.signal.aborted) return;
				errorNotice.hidden = false;
				callback({ draw: request.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
			}
		},
	});

	function applyFilters() {
		if (clearing) return;
		const params = new URLSearchParams(new FormData(form));
		const operation = form.elements.operation_id.value;
		operationNotice.hidden = !operation;
		operationNotice.querySelector('code').textContent = operation;
		const url = new URL(window.location.href);
		url.search = params.toString();
		window.history.replaceState(null, '', url);
		table.ajax.reload(null, true);
	}
	form.addEventListener('submit', function (event) { event.preventDefault(); applyFilters(); });
	form.addEventListener('change', applyFilters);
	document.getElementById('history-clear-filters').addEventListener('click', function (event) {
		event.preventDefault();
		clearing = true;
		pickers.forEach(function (picker) { picker.setSelected(''); });
		form.elements.history_action.value = '';
		form.elements.from.value = '';
		form.elements.to.value = '';
		form.elements.operation_id.value = '';
		clearing = false;
		table.search('');
		applyFilters();
	});
	tableElement.addEventListener('click', function (event) {
		const link = event.target.closest('[data-history-operation]');
		if (!link) return;
		event.preventDefault();
		form.elements.operation_id.value = link.dataset.historyOperation;
		applyFilters();
	});
})();
