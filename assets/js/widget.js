/*
 * CASABLANCA booking widget — client-side glue.
 *
 *   1. Multi-room occupancy UI (1–5 rooms, adults/children per room).
 *   2. Search bar → IBE v2 deep link.
 *   3. Availability widget → live calendar fetch via TYPO3 (B2B API proxy).
 *   4. Calendar → auto-load, month viewport, from/to selection, Book now → IBE.
 */
(function () {
    'use strict';

    var MAX_ROOMS = 5;
    var calendarSelections = new WeakMap();

    function clamp(n, lo, hi) {
        n = parseInt(n, 10);
        if (isNaN(n)) n = lo;
        return Math.min(Math.max(n, lo), hi);
    }

    var CALENDAR_MOBILE_MAX_WIDTH = 640;

    function isCalendarMobileViewport() {
        return window.matchMedia('(max-width: ' + CALENDAR_MOBILE_MAX_WIDTH + 'px)').matches;
    }

    function resolveVisibleMonthCount(form) {
        var configured = clamp(parseInt(form.getAttribute('data-cb-initial-months') || '1', 10), 1, 2);
        return isCalendarMobileViewport() ? 1 : configured;
    }

    function label(template, n) {
        if (!template) {
            return String(n);
        }
        return String(template).replace('{0}', String(n)).replace('%s', String(n));
    }

    function readAges(block, count) {
        var container = block.querySelector('[data-cb-ages-list]');
        if (!container) return [];
        var inputs = Array.prototype.slice.call(
            container.querySelectorAll('[data-cb-child-age]')
        );
        var ages = inputs.map(function (el) {
            return clamp(el.value || '0', 0, 17);
        });
        while (ages.length < count) ages.push(0);
        if (ages.length > count) ages = ages.slice(0, count);
        return ages;
    }

    function syncHiddenChildrenAges(block) {
        var hidden = block.querySelector('[data-cb-children-ages-hidden]');
        if (!hidden) return;
        var countEl = block.querySelector('[data-cb-field="children-count"]');
        var count = countEl ? clamp(countEl.value || '0', 0, 10) : 0;
        hidden.value = readAges(block, count).join(',');
    }

    function syncChildAgeInputs(block) {
        var countEl = block.querySelector('[data-cb-field="children-count"]');
        var container = block.querySelector('[data-cb-ages-container]');
        var list = block.querySelector('[data-cb-ages-list]');
        if (!countEl || !container || !list) return;

        var count = clamp(countEl.value || '0', 0, 10);
        var existing = Array.prototype.slice.call(
            list.querySelectorAll('[data-cb-child-age]')
        ).map(function (el) { return el.value; });
        var hidden = block.querySelector('[data-cb-children-ages-hidden]');
        var hiddenAges = hidden && String(hidden.value || '').trim() !== ''
            ? String(hidden.value).split(/\s*,\s*/)
            : [];

        list.innerHTML = '';
        for (var i = 0; i < count; i++) {
            var ageLabel = document.createElement('label');
            ageLabel.className = 'cb-ages__item';

            var span = document.createElement('span');
            span.className = 'cb-ages__label';
            span.textContent = label(
                window.CB_LABELS && window.CB_LABELS.childN,
                i + 1
            );

            var input = document.createElement('input');
            input.type = 'number';
            input.name = 'rooms[' + (block.getAttribute('data-cb-room-index') || '0') + '][childAge][]';
            input.min = '0';
            input.max = '17';
            input.required = true;
            if (existing[i] !== undefined && existing[i] !== '') {
                input.value = existing[i];
            } else if (hiddenAges[i] !== undefined && hiddenAges[i] !== '') {
                input.value = String(parseInt(hiddenAges[i], 10) || 0);
            } else {
                input.value = '12';
            }
            input.setAttribute('data-cb-child-age', '');

            ageLabel.appendChild(span);
            ageLabel.appendChild(input);
            list.appendChild(ageLabel);
        }

        if (count > 0) {
            container.removeAttribute('hidden');
        } else {
            container.setAttribute('hidden', 'hidden');
        }

        syncHiddenChildrenAges(block);
    }

    function cbText(key, fallback) {
        var labels = window.CB_LABELS || {};
        var value = labels[key];
        return value !== undefined && value !== null && value !== '' ? value : fallback;
    }

    function getFormDefaultOccupancy(form) {
        var adults = 2;
        var children = 0;
        var ages = [];
        if (form) {
            adults = clamp(form.getAttribute('data-cb-default-adults') || '2', 1, 10);
            children = clamp(form.getAttribute('data-cb-default-children') || '0', 0, 10);
            var rawAges = String(form.getAttribute('data-cb-default-children-ages') || '').trim();
            if (rawAges !== '') {
                ages = rawAges.split(/\s*,\s*/).map(function (part) {
                    return parseInt(part, 10);
                }).filter(function (age) {
                    return !isNaN(age) && age >= 0;
                });
            }
        }
        while (ages.length < children) {
            ages.push(12);
        }
        ages = ages.slice(0, children);
        return { adults: adults, children: children, ages: ages };
    }

    function createRoomBlock(index, defaults) {
        defaults = defaults || { adults: 2, children: 0, ages: [] };
        var adults = clamp(defaults.adults || 2, 1, 10);
        var children = clamp(defaults.children || 0, 0, 10);
        var ages = Array.isArray(defaults.ages) ? defaults.ages.slice(0, children) : [];
        while (ages.length < children) {
            ages.push(12);
        }

        var block = document.createElement('fieldset');
        block.className = 'cb-room-block';
        block.setAttribute('data-cb-room-block', '');
        block.setAttribute('data-cb-room-index', String(index));

        var legend = document.createElement('legend');
        legend.className = 'cb-room-block__title';
        legend.textContent = label(
            window.CB_LABELS && window.CB_LABELS.roomN,
            index + 1
        );
        block.appendChild(legend);

        var adultsLabel = document.createElement('label');
        adultsLabel.className = 'cb-field';
        adultsLabel.innerHTML = '<span class="cb-field__label">' + cbText('adults', 'Adults') + '</span>'
            + '<input type="number" name="rooms[' + index + '][adults]" min="1" max="10" value="' + adults + '" data-cb-field="adults" required />';
        block.appendChild(adultsLabel);

        var childrenLabel = document.createElement('label');
        childrenLabel.className = 'cb-field';
        childrenLabel.innerHTML = '<span class="cb-field__label">' + cbText('children', 'Children') + '</span>'
            + '<input type="number" name="rooms[' + index + '][children]" min="0" max="10" value="' + children + '" data-cb-field="children-count" required />';
        block.appendChild(childrenLabel);

        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'rooms[' + index + '][childrenAges]';
        hidden.value = ages.join(', ');
        hidden.setAttribute('data-cb-children-ages-hidden', '');
        block.appendChild(hidden);

        var agesWrap = document.createElement('div');
        agesWrap.className = 'cb-field cb-field--ages';
        agesWrap.setAttribute('data-cb-ages-container', '');
        if (children <= 0) {
            agesWrap.setAttribute('hidden', 'hidden');
        }
        agesWrap.innerHTML = '<span class="cb-field__label cb-field__label--group">' + cbText('childrenAges', 'Children ages') + '</span>'
            + '<div class="cb-ages" data-cb-ages-list></div>';
        block.appendChild(agesWrap);

        return block;
    }

    function parseRoomCount(raw, fallback) {
        var trimmed = String(raw).trim();
        if (trimmed === '') {
            return fallback !== undefined ? fallback : null;
        }
        var n = parseInt(trimmed, 10);
        if (isNaN(n)) {
            return fallback !== undefined ? fallback : null;
        }
        return n;
    }

    function syncRoomBlocks(form, options) {
        options = options || {};
        var countEl = form.querySelector('[data-cb-field="rooms-count"]');
        var list = form.querySelector('[data-cb-rooms-list]');
        if (!countEl || !list) return;

        var count;
        if (options.finalize) {
            count = clamp(parseRoomCount(countEl.value, 1), 1, MAX_ROOMS);
            countEl.value = String(count);
        } else {
            count = parseRoomCount(countEl.value);
            if (count === null || count < 1 || count > MAX_ROOMS) {
                return;
            }
        }

        var blocks = Array.prototype.slice.call(
            list.querySelectorAll('[data-cb-room-block]')
        );

        var roomDefaults = getFormDefaultOccupancy(form);
        while (blocks.length < count) {
            var block = createRoomBlock(blocks.length, roomDefaults);
            list.appendChild(block);
            attachRoomBlock(block);
            syncChildAgeInputs(block);
            blocks.push(block);
        }

        while (blocks.length > count) {
            var last = blocks.pop();
            if (last && last.parentNode) {
                last.parentNode.removeChild(last);
            }
        }

        blocks.forEach(function (block, index) {
            block.setAttribute('data-cb-room-index', String(index));
            var legend = block.querySelector('legend');
            if (legend) {
                legend.textContent = label(
                    window.CB_LABELS && window.CB_LABELS.roomN,
                    index + 1
                );
            }
            block.querySelector('[data-cb-field="adults"]').name = 'rooms[' + index + '][adults]';
            block.querySelector('[data-cb-field="children-count"]').name = 'rooms[' + index + '][children]';
            block.querySelector('[data-cb-children-ages-hidden]').name = 'rooms[' + index + '][childrenAges]';
            block.querySelectorAll('[data-cb-child-age]').forEach(function (input) {
                input.name = 'rooms[' + index + '][childAge][]';
            });
        });
    }

    function attachRoomBlock(block) {
        var countEl = block.querySelector('[data-cb-field="children-count"]');
        if (countEl) {
            countEl.addEventListener('input', function () { syncChildAgeInputs(block); });
            countEl.addEventListener('change', function () { syncChildAgeInputs(block); });
        }
    }

    function readRoomOccupancies(form) {
        var rooms = [];
        var blocks = form.querySelectorAll('[data-cb-room-block]');
        blocks.forEach(function (block) {
            var adultsEl = block.querySelector('[data-cb-field="adults"]');
            var childrenEl = block.querySelector('[data-cb-field="children-count"]');
            var adults = clamp(adultsEl ? adultsEl.value : '2', 1, 10);
            var childCount = clamp(childrenEl ? childrenEl.value : '0', 0, 10);
            rooms.push({
                adults: adults,
                children: childCount,
                ages: readAges(block, childCount)
            });
        });
        return rooms;
    }

    function openIbeUrl(source, url) {
        if (!url) {
            return;
        }
        var target = source.getAttribute('data-cb-link-target') || '_self';
        if (target === '_blank') {
            window.open(url, '_blank', 'noopener,noreferrer');
        } else {
            window.location.assign(url);
        }
    }

    function buildIbeUrl(source, overrides) {
        overrides = overrides || {};
        var base = source.getAttribute('data-cb-ibe-base');
        var tenant = source.getAttribute('data-cb-tenant');
        var context = source.getAttribute('data-cb-ibe-context');
        var culture = source.getAttribute('data-cb-culture') || '';
        if (!culture) {
            return null;
        }
        var linkStyle = source.getAttribute('data-cb-link-style') || 'full_path';
        if (linkStyle === 'tenant_only') {
            linkStyle = 'culture_space';
        }

        if (!base) return null;
        if (linkStyle === 'full_path' && (!tenant || !context)) return null;
        if (linkStyle === 'culture_space' && !context) return null;

        var form = source.tagName === 'FORM' ? source : source.closest('form');
        var getField = function (name) {
            if (Object.prototype.hasOwnProperty.call(overrides, name)) {
                return overrides[name];
            }
            if (!form) return '';
            var el = form.querySelector('[data-cb-field="' + name + '"]');
            return el ? el.value : '';
        };

        var arrival = getField('arrival');
        var departure = getField('departure');
        var stayNights = source.getAttribute('data-cb-stay-nights');

        if (!departure && arrival && stayNights) {
            departure = defaultDeparture(arrival, stayNights);
        }
        if (!arrival || !departure) return null;
        if (departure <= arrival) return null;

        var rooms = form ? readRoomOccupancies(form) : [{
            adults: clamp(source.getAttribute('data-cb-adults') || '2', 1, 20),
            children: clamp(source.getAttribute('data-cb-children') || '0', 0, 10),
            ages: []
        }];
        if (rooms.length === 0) {
            rooms = [{ adults: 2, children: 0, ages: [] }];
        }

        var params = new URLSearchParams();
        params.set('arrivalDate', arrival);
        params.set('departureDate', departure);
        if (rooms.length > 1) {
            params.set('numberOfRooms', String(rooms.length));
        }

        rooms.forEach(function (room, index) {
            params.set('rooms_' + index + '__adults', String(room.adults));
            if (room.children > 0) {
                params.set('rooms_' + index + '__children', String(room.children));
                room.ages.forEach(function (age, childIndex) {
                    params.set(
                        'rooms_' + index + '__children_' + childIndex + '__age',
                        String(age)
                    );
                });
            }
        });

        var roomTypeId = source.getAttribute('data-cb-room-type')
            || source.getAttribute('data-cb-preselected-room')
            || '';
        if (roomTypeId) {
            params.set('roomTypeIds', roomTypeId);
        }

        var rateIds = '';
        if (Object.prototype.hasOwnProperty.call(overrides, 'rateIds')) {
            rateIds = overrides.rateIds || '';
        } else {
            rateIds = source.getAttribute('data-cb-rate-ids') || '';
        }
        if (rateIds) {
            params.set('rateIds', rateIds);
        }

        var segments = [base.replace(/\/+$/, ''), encodeURIComponent(culture)];
        if (linkStyle === 'culture_space') {
            segments.push(encodeURIComponent(context));
        } else if (linkStyle === 'full_path') {
            segments.push(encodeURIComponent(tenant));
            segments.push(encodeURIComponent(context));
        }

        return segments.join('/') + '?' + params.toString();
    }

    function defaultDeparture(arrival, minStay) {
        var d = new Date(arrival + 'T00:00:00');
        if (isNaN(d.getTime())) return '';
        var nights = Math.max(parseInt(minStay, 10) || 1, 1);
        d.setDate(d.getDate() + nights);
        var mm = String(d.getMonth() + 1).padStart(2, '0');
        var dd = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + mm + '-' + dd;
    }

    function serializeCalendarRequest(form) {
        var data = new FormData(form);
        data.set('windowDays', form.getAttribute('data-cb-window-days') || '90');
        var culture = form.getAttribute('data-cb-culture');
        if (culture) {
            data.set('culture', culture);
        }
        var filterRateIds = form.getAttribute('data-cb-filter-rate-ids');
        if (filterRateIds) {
            data.set('rateIds', filterRateIds);
        }
        return data;
    }

    function formatPrice(price, currency) {
        if (price === null || price === undefined || price === '') return '';
        var num = parseFloat(price);
        if (isNaN(num)) return '';
        return num.toFixed(2).replace('.', ',') + ' ' + (currency || 'EUR');
    }

    function formatCalendarDate(isoDate) {
        if (!isoDate) return '—';
        var d = new Date(isoDate + 'T00:00:00');
        if (isNaN(d.getTime())) return isoDate;
        return d.toLocaleDateString(undefined, {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
    }

    function formatPriceDisplay(price, currency) {
        if (price === null || price === undefined || price === '') return '—';
        var num = parseFloat(price);
        if (isNaN(num)) return '—';
        var symbol = (currency || 'EUR') === 'EUR' ? '€' : (currency || 'EUR');
        return symbol + ' ' + num.toFixed(2).replace('.', ',');
    }

    function getTodayIso() {
        var d = new Date();
        var mm = String(d.getMonth() + 1).padStart(2, '0');
        var dd = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + mm + '-' + dd;
    }

    function getCalendarWidget(form) {
        return form ? form.closest('.cb-widget') : null;
    }

    function getCalendarState(form) {
        if (!calendarSelections.has(form)) {
            calendarSelections.set(form, {
                selectedFrom: null,
                selectedTo: null,
                nextPick: 'from',
                fromMinStay: 1,
                months: {},
                monthKeys: [],
                dayMap: {},
                visibleStartIndex: 0,
                visibleCount: 1,
                refetchTimer: null,
                offersRefetchTimer: null,
                offersRequestId: 0,
                selectedRateId: null,
                offerTotalPrice: null,
                offerCurrency: 'EUR'
            });
        }
        return calendarSelections.get(form);
    }

    function buildDayMap(months) {
        var map = {};
        Object.keys(months || {}).forEach(function (monthKey) {
            (months[monthKey] || []).forEach(function (day) {
                map[day.date] = day;
            });
        });
        return map;
    }

    function getWeekdayLabels() {
        var labels = [];
        var monday = new Date('2024-01-01T00:00:00');
        for (var i = 0; i < 7; i++) {
            var d = new Date(monday);
            d.setDate(monday.getDate() + i);
            labels.push(d.toLocaleDateString(undefined, { weekday: 'short' }).replace(/\.$/, '').toUpperCase());
        }
        return labels;
    }

    function canPickFrom(cell) {
        return cell.getAttribute('data-cb-is-past') !== '1'
            && cell.getAttribute('data-cb-available') === '1'
            && cell.getAttribute('data-cb-arrival-allowed') !== '0';
    }

    function pickFromDate(form, cell, date) {
        var state = getCalendarState(form);
        state.selectedFrom = date;
        state.selectedTo = null;
        state.fromMinStay = parseInt(cell.getAttribute('data-cb-min-stay') || '1', 10) || 1;
        state.nextPick = 'to';
    }

    function pickToDate(form, date) {
        var state = getCalendarState(form);
        var minDeparture = defaultDeparture(state.selectedFrom, state.fromMinStay);
        if (!minDeparture || date <= state.selectedFrom) {
            return false;
        }
        state.selectedTo = date < minDeparture ? minDeparture : date;
        state.nextPick = 'from';
        return true;
    }

    function getOfferMode(form) {
        return form.getAttribute('data-cb-offer-mode') || 'none';
    }

    function offersEnabled(form) {
        return getOfferMode(form) !== 'none'
            && form.getAttribute('data-cb-offers-url') !== '';
    }

    function countNights(arrival, departure) {
        if (!arrival || !departure) return 0;
        var start = new Date(arrival + 'T00:00:00');
        var end = new Date(departure + 'T00:00:00');
        if (isNaN(start.getTime()) || isNaN(end.getTime()) || end <= start) return 0;
        return Math.round((end - start) / 86400000);
    }

    function clearOffersSelection(form) {
        var state = getCalendarState(form);
        state.selectedRateId = null;
        state.offerTotalPrice = null;
        state.offerCurrency = 'EUR';
        form.removeAttribute('data-cb-rate-ids');

        var widget = getCalendarWidget(form);
        if (!widget) return;

        var offersPanel = widget.querySelector('[data-cb-offers]');
        var offersList = widget.querySelector('[data-cb-offers-list]');
        var offersLoading = widget.querySelector('[data-cb-offers-loading]');
        var offersError = widget.querySelector('[data-cb-offers-error]');

        if (offersList) offersList.innerHTML = '';
        if (offersLoading) offersLoading.setAttribute('hidden', 'hidden');
        if (offersError) {
            offersError.setAttribute('hidden', 'hidden');
            offersError.textContent = '';
        }
        if (offersPanel) offersPanel.setAttribute('hidden', 'hidden');
    }

    function clearCalendarSelection(form) {
        var state = getCalendarState(form);
        state.selectedFrom = null;
        state.selectedTo = null;
        state.nextPick = 'from';
        state.fromMinStay = 1;
        clearOffersSelection(form);
        applyCalendarSelection(form);
    }

    function updateRangeChrome(form) {
        var widget = getCalendarWidget(form);
        if (!widget) return;

        var state = getCalendarState(form);
        widget.querySelectorAll('.cb-calendar__clear, .cb-calendar__nights').forEach(function (el) {
            el.parentNode.removeChild(el);
        });

        if (!state.selectedFrom || !state.selectedTo) return;

        var toCell = widget.querySelector('.cb-calendar__cell--to[data-cb-date]');
        if (!toCell) return;

        var nights = countNights(state.selectedFrom, state.selectedTo);
        if (nights > 0) {
            var nightsEl = document.createElement('span');
            nightsEl.className = 'cb-calendar__nights';
            nightsEl.textContent = label(
                window.CB_LABELS && window.CB_LABELS.calendarNights,
                nights
            );
            toCell.appendChild(nightsEl);
        }

        var clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.className = 'cb-calendar__clear';
        clearBtn.setAttribute('aria-label', window.CB_LABELS && window.CB_LABELS.calendarClearSelection
            ? window.CB_LABELS.calendarClearSelection
            : 'Clear date selection');
        clearBtn.textContent = '×';
        clearBtn.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            clearCalendarSelection(form);
        });
        toCell.appendChild(clearBtn);
    }

    function selectOffer(form, offer) {
        var state = getCalendarState(form);
        state.selectedRateId = offer.rateId || null;
        state.offerTotalPrice = offer.totalPrice != null ? parseFloat(offer.totalPrice) : null;
        state.offerCurrency = offer.currency || 'EUR';

        if (state.selectedRateId) {
            form.setAttribute('data-cb-rate-ids', state.selectedRateId);
        } else {
            form.removeAttribute('data-cb-rate-ids');
        }

        updatePriceSummary(form);
    }

    function renderOffers(form, payload) {
        var widget = getCalendarWidget(form);
        if (!widget) return;

        var offersPanel = widget.querySelector('[data-cb-offers]');
        var offersList = widget.querySelector('[data-cb-offers-list]');
        var offersLoading = widget.querySelector('[data-cb-offers-loading]');
        var offersError = widget.querySelector('[data-cb-offers-error]');
        if (!offersPanel || !offersList) return;

        if (offersLoading) offersLoading.setAttribute('hidden', 'hidden');
        if (offersError) {
            offersError.setAttribute('hidden', 'hidden');
            offersError.textContent = '';
        }

        offersList.innerHTML = '';
        var offers = payload && payload.offers ? payload.offers : [];
        if (offers.length === 0) {
            offersPanel.setAttribute('hidden', 'hidden');
            clearOffersSelection(form);
            updatePriceSummary(form);
            return;
        }

        offersPanel.removeAttribute('hidden');
        var offerMode = getOfferMode(form);
        var lastSection = '';

        offers.forEach(function (offer, index) {
            if (offerMode === 'packages_and_rates' && offer.section && offer.section !== lastSection) {
                lastSection = offer.section;
                var heading = document.createElement('p');
                heading.className = 'cb-calendar__offers-heading';
                if (offer.section === 'packages') {
                    heading.textContent = window.CB_LABELS && window.CB_LABELS.calendarPackagesHeading
                        ? window.CB_LABELS.calendarPackagesHeading
                        : 'Packages';
                } else {
                    heading.textContent = window.CB_LABELS && window.CB_LABELS.calendarRatesHeading
                        ? window.CB_LABELS.calendarRatesHeading
                        : 'Rates';
                }
                offersList.appendChild(heading);
            }

            var item = document.createElement('label');
            item.className = 'cb-calendar__offer';

            var input = document.createElement('input');
            input.type = 'radio';
            input.name = 'cb-calendar-offer';
            input.value = offer.rateId || String(index);
            input.className = 'cb-calendar__offer-input';
            if (index === 0) input.checked = true;

            var body = document.createElement('span');
            body.className = 'cb-calendar__offer-body';

            var title = document.createElement('span');
            title.className = 'cb-calendar__offer-title';
            title.textContent = offer.name || offer.rateId || '';

            body.appendChild(title);

            var offerSubtitle = offer.subtitle != null ? String(offer.subtitle).trim() : '';
            if (offerSubtitle !== '' && offerSubtitle.toLowerCase() !== 'undefined') {
                var subtitle = document.createElement('span');
                subtitle.className = 'cb-calendar__offer-subtitle';
                subtitle.textContent = offerSubtitle;
                body.appendChild(subtitle);
            }

            if (offer.detailUrl) {
                var details = document.createElement('a');
                details.className = 'cb-calendar__offer-details';
                details.href = offer.detailUrl;
                details.textContent = window.CB_LABELS && window.CB_LABELS.calendarDetails
                    ? window.CB_LABELS.calendarDetails
                    : 'Details';
                details.addEventListener('click', function (event) {
                    event.stopPropagation();
                });
                body.appendChild(details);
            }

            var price = document.createElement('span');
            price.className = 'cb-calendar__offer-price';
            price.textContent = formatPriceDisplay(offer.totalPrice, offer.currency || 'EUR');

            item.appendChild(input);
            item.appendChild(body);
            item.appendChild(price);

            input.addEventListener('change', function () {
                selectOffer(form, offer);
            });

            offersList.appendChild(item);
        });

        selectOffer(form, offers[0]);
    }

    function fetchOffers(form) {
        if (!offersEnabled(form)) return Promise.resolve();

        var state = getCalendarState(form);
        if (!state.selectedFrom || !state.selectedTo) {
            clearOffersSelection(form);
            return Promise.resolve();
        }

        var widget = getCalendarWidget(form);
        var url = form.getAttribute('data-cb-offers-url');
        if (!widget || !url) return Promise.resolve();

        var offersPanel = widget.querySelector('[data-cb-offers]');
        var offersLoading = widget.querySelector('[data-cb-offers-loading]');
        var offersError = widget.querySelector('[data-cb-offers-error]');

        if (offersPanel) offersPanel.removeAttribute('hidden');
        if (offersLoading) offersLoading.removeAttribute('hidden');
        if (offersError) {
            offersError.setAttribute('hidden', 'hidden');
            offersError.textContent = '';
        }

        state.offersRequestId += 1;
        var requestId = state.offersRequestId;

        var params = new URLSearchParams(serializeCalendarRequest(form));
        params.set('offerMode', getOfferMode(form));
        var separator = url.indexOf('?') >= 0 ? '&' : '?';

        return fetch(url + separator + params.toString(), {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        }).then(function (response) {
            return response.text().then(function (body) {
                var payload = null;
                if (body) {
                    try {
                        payload = JSON.parse(body);
                    } catch (parseError) {
                        throw new Error('HTTP ' + response.status);
                    }
                }
                if (!response.ok) {
                    var httpMessage = payload && (payload.message || payload.error)
                        ? payload.message || payload.error
                        : 'HTTP ' + response.status;
                    throw new Error(httpMessage);
                }
                return payload || {};
            });
        }).then(function (payload) {
            if (requestId !== state.offersRequestId) return;
            if (payload.error) throw new Error(payload.message || payload.error);
            renderOffers(form, payload);
        }).catch(function (error) {
            if (requestId !== state.offersRequestId) return;
            clearOffersSelection(form);
            if (offersLoading) offersLoading.setAttribute('hidden', 'hidden');
            if (offersError) {
                var fallback = window.CB_LABELS && window.CB_LABELS.calendarOffersError
                    ? window.CB_LABELS.calendarOffersError
                    : 'Could not load offers.';
                offersError.textContent = error && error.message ? error.message : fallback;
                offersError.removeAttribute('hidden');
            }
            updatePriceSummary(form);
            console.warn('[CASABLANCA offers]', error);
        });
    }

    function scheduleOffersRefetch(form) {
        if (!offersEnabled(form)) return;
        var state = getCalendarState(form);
        if (state.offersRefetchTimer) clearTimeout(state.offersRefetchTimer);
        state.offersRefetchTimer = setTimeout(function () {
            fetchOffers(form);
        }, 400);
    }

    function lowestPriceInMonths(state, monthKeys) {
        var lowest = null;
        var currency = 'EUR';
        monthKeys.forEach(function (monthKey) {
            (state.months[monthKey] || []).forEach(function (day) {
                if (!day.isAvailable || day.fromPrice == null) return;
                var price = parseFloat(day.fromPrice);
                if (isNaN(price)) return;
                if (lowest === null || price < lowest) {
                    lowest = price;
                    currency = day.currency || 'EUR';
                }
            });
        });
        return lowest === null ? null : { price: lowest, currency: currency };
    }

    function updatePriceSummary(form) {
        var widget = getCalendarWidget(form);
        if (!widget) return;

        var state = getCalendarState(form);
        var valueEl = widget.querySelector('[data-cb-price-value]');
        if (!valueEl) return;

        var priceInfo = null;
        if (
            state.selectedFrom
            && state.selectedTo
            && state.offerTotalPrice != null
            && !isNaN(state.offerTotalPrice)
        ) {
            priceInfo = {
                price: state.offerTotalPrice,
                currency: state.offerCurrency || 'EUR'
            };
        } else if (state.selectedFrom) {
            var fromCell = widget.querySelector('.cb-calendar__cell[data-cb-date="' + state.selectedFrom + '"]');
            if (fromCell) {
                var p = fromCell.getAttribute('data-cb-price');
                var c = fromCell.getAttribute('data-cb-currency') || 'EUR';
                if (p) priceInfo = { price: parseFloat(p), currency: c };
            }
            if (!priceInfo && state.dayMap[state.selectedFrom]) {
                var day = state.dayMap[state.selectedFrom];
                priceInfo = { price: day.fromPrice, currency: day.currency || 'EUR' };
            }
        }

        if (!priceInfo) {
            var visibleKeys = state.monthKeys.slice(
                state.visibleStartIndex,
                state.visibleStartIndex + state.visibleCount
            );
            priceInfo = lowestPriceInMonths(state, visibleKeys);
        }

        valueEl.textContent = priceInfo
            ? formatPriceDisplay(priceInfo.price, priceInfo.currency)
            : '—';
    }

    function applyCalendarSelection(form) {
        var widget = getCalendarWidget(form);
        if (!widget) return;

        var state = getCalendarState(form);
        var arrivalEl = form.querySelector('[data-cb-field="arrival"]');
        var departureEl = form.querySelector('[data-cb-field="departure"]');

        if (arrivalEl) arrivalEl.value = state.selectedFrom || '';
        if (departureEl) {
            departureEl.value = state.selectedTo || '';
            if (state.selectedFrom) departureEl.min = state.selectedFrom;
        }

        var summary = widget.querySelector('[data-cb-selection-summary]');
        var fromEl = widget.querySelector('[data-cb-selection-from]');
        var toEl = widget.querySelector('[data-cb-selection-to]');
        if (fromEl) fromEl.textContent = formatCalendarDate(state.selectedFrom);
        if (toEl) toEl.textContent = formatCalendarDate(state.selectedTo);
        if (summary) {
            if (state.selectedFrom) {
                summary.removeAttribute('hidden');
            } else {
                summary.setAttribute('hidden', 'hidden');
            }
        }

        var bookBtn = widget.querySelector('[data-cb-book-now]');
        if (bookBtn) {
            bookBtn.disabled = !(state.selectedFrom && state.selectedTo);
        }

        widget.querySelectorAll('.cb-calendar__cell').forEach(function (cell) {
            cell.classList.remove(
                'cb-calendar__cell--from',
                'cb-calendar__cell--to',
                'cb-calendar__cell--in-range'
            );

            var date = cell.getAttribute('data-cb-date');
            if (!date || !state.selectedFrom) return;

            if (date === state.selectedFrom) cell.classList.add('cb-calendar__cell--from');
            if (state.selectedTo && date === state.selectedTo) cell.classList.add('cb-calendar__cell--to');
            if (state.selectedTo && date > state.selectedFrom && date < state.selectedTo) {
                cell.classList.add('cb-calendar__cell--in-range');
            }
        });

        widget.classList.toggle('cb-widget--pick-to', state.nextPick === 'to');
        widget.classList.toggle('cb-widget--pick-from', state.nextPick === 'from');
        updateRangeChrome(form);
        updatePriceSummary(form);

        if (state.selectedFrom && state.selectedTo && offersEnabled(form)) {
            fetchOffers(form);
        } else if (!state.selectedFrom || !state.selectedTo) {
            clearOffersSelection(form);
        }
    }

    function handleCalendarCellClick(form, cell) {
        if (cell.getAttribute('data-cb-is-past') === '1') return;

        var date = cell.getAttribute('data-cb-date');
        if (!date) return;

        var state = getCalendarState(form);

        if (state.nextPick === 'from') {
            if (!canPickFrom(cell)) return;
            pickFromDate(form, cell, date);
        } else if (date <= state.selectedFrom) {
            if (!canPickFrom(cell)) return;
            pickFromDate(form, cell, date);
        } else {
            pickToDate(form, date);
        }

        applyCalendarSelection(form);
    }

    function createCalendarCell(dateStr, day, today) {
        var cell = document.createElement('li');
        cell.setAttribute('data-cb-date', dateStr);
        cell.setAttribute('role', 'listitem');

        var dateSpan = document.createElement('span');
        dateSpan.className = 'cb-calendar__date';
        dateSpan.textContent = String(parseInt(dateStr.split('-')[2], 10));
        cell.appendChild(dateSpan);

        if (dateStr < today) {
            cell.className = 'cb-calendar__cell cb-calendar__cell--past';
            cell.setAttribute('data-cb-is-past', '1');
            cell.setAttribute('data-cb-available', '0');
            cell.setAttribute('aria-hidden', 'true');
            var mark = document.createElement('span');
            mark.className = 'cb-calendar__past-mark';
            mark.setAttribute('aria-hidden', 'true');
            mark.textContent = '×';
            cell.appendChild(mark);
            return cell;
        }

        if (!day) {
            cell.className = 'cb-calendar__cell cb-state-unavailable';
            cell.setAttribute('data-cb-available', '0');
            cell.setAttribute('data-cb-arrival-allowed', '0');
            cell.setAttribute('tabindex', '-1');
            return cell;
        }

        cell.className = 'cb-calendar__cell ' + (day.calendarStateClass || '');
        cell.setAttribute('data-cb-available', day.isAvailable ? '1' : '0');
        cell.setAttribute('data-cb-arrival-allowed', day.isArrivalAllowed !== false ? '1' : '0');
        cell.setAttribute('data-cb-room', day.roomTypeId || '');
        cell.setAttribute('data-cb-min-stay', String(day.minLengthOfStay || 1));
        cell.setAttribute('data-cb-price', day.fromPrice != null ? String(day.fromPrice) : '');
        cell.setAttribute('data-cb-currency', day.currency || 'EUR');
        cell.setAttribute('tabindex', day.isAvailable ? '0' : '-1');

        if (day.isAvailable && day.fromPrice != null) {
            var priceSpan = document.createElement('span');
            priceSpan.className = 'cb-calendar__price';
            priceSpan.textContent = formatPrice(day.fromPrice, day.currency);
            cell.appendChild(priceSpan);
        }

        if (day.hasRestrictions) {
            var flag = document.createElement('span');
            flag.className = 'cb-calendar__flag';
            flag.textContent = '*';
            flag.setAttribute('title', window.CB_LABELS && window.CB_LABELS.restrictions
                ? window.CB_LABELS.restrictions
                : 'Restrictions apply');
            flag.setAttribute('aria-hidden', 'true');
            cell.appendChild(flag);
        }

        return cell;
    }

    function renderMonthSection(monthKey, state, today) {
        var section = document.createElement('section');
        section.className = 'cb-calendar__month';
        section.setAttribute('data-cb-month', monthKey);

        var parts = monthKey.split('-');
        var year = parseInt(parts[0], 10);
        var month = parseInt(parts[1], 10);
        var daysInMonth = new Date(year, month, 0).getDate();
        var firstDay = new Date(monthKey + '-01T00:00:00');
        var startOffset = (firstDay.getDay() + 6) % 7;

        var weekdays = document.createElement('ol');
        weekdays.className = 'cb-calendar__weekdays';
        weekdays.setAttribute('aria-hidden', 'true');
        getWeekdayLabels().forEach(function (label) {
            var li = document.createElement('li');
            li.className = 'cb-calendar__weekday';
            li.textContent = label;
            weekdays.appendChild(li);
        });
        section.appendChild(weekdays);

        var grid = document.createElement('ol');
        grid.className = 'cb-calendar__grid';
        grid.setAttribute('role', 'list');

        for (var pad = 0; pad < startOffset; pad++) {
            var empty = document.createElement('li');
            empty.className = 'cb-calendar__cell cb-calendar__cell--pad';
            empty.setAttribute('aria-hidden', 'true');
            grid.appendChild(empty);
        }

        for (var dayNum = 1; dayNum <= daysInMonth; dayNum++) {
            var mm = String(month).padStart(2, '0');
            var dd = String(dayNum).padStart(2, '0');
            var dateStr = year + '-' + mm + '-' + dd;
            var day = state.dayMap[dateStr] || null;
            grid.appendChild(createCalendarCell(dateStr, day, today));
        }

        section.appendChild(grid);
        return section;
    }

    function updateMonthNav(form) {
        var widget = getCalendarWidget(form);
        if (!widget) return;

        var state = getCalendarState(form);
        var nav = widget.querySelector('[data-cb-calendar-nav]');
        var titleEl = widget.querySelector('[data-cb-month-title]');
        var prevBtn = widget.querySelector('[data-cb-month-prev]');
        var nextBtn = widget.querySelector('[data-cb-month-next]');

        if (!nav || !titleEl) return;

        if (state.monthKeys.length === 0) {
            nav.setAttribute('hidden', 'hidden');
            return;
        }

        nav.removeAttribute('hidden');

        var visibleKeys = state.monthKeys.slice(
            state.visibleStartIndex,
            state.visibleStartIndex + state.visibleCount
        );
        var titles = visibleKeys.map(function (monthKey) {
            var monthDate = new Date(monthKey + '-01T00:00:00');
            return monthDate.toLocaleDateString(undefined, {
                month: 'long',
                year: 'numeric'
            });
        });
        titleEl.textContent = titles.join(' – ').toUpperCase();

        if (prevBtn) {
            prevBtn.disabled = state.visibleStartIndex <= 0;
        }
        if (nextBtn) {
            nextBtn.disabled = state.visibleStartIndex + state.visibleCount >= state.monthKeys.length;
        }
    }

    function renderCalendarViewport(form) {
        var widget = getCalendarWidget(form);
        if (!widget) return;

        var state = getCalendarState(form);
        var host = widget.querySelector('[data-cb-calendar-host]');
        if (!host) return;

        host.innerHTML = '';
        host.classList.remove('cb-calendar--empty');

        if (state.monthKeys.length === 0) {
            host.innerHTML = '<p class="cb-calendar__status">' + (
                window.CB_LABELS && window.CB_LABELS.calendarError
                    ? window.CB_LABELS.calendarError
                    : 'Could not load availability.'
            ) + '</p>';
            return;
        }

        var today = getTodayIso();
        var visibleKeys = state.monthKeys.slice(
            state.visibleStartIndex,
            state.visibleStartIndex + state.visibleCount
        );

        visibleKeys.forEach(function (monthKey) {
            host.appendChild(renderMonthSection(monthKey, state, today));
        });

        host.removeAttribute('hidden');
        updateMonthNav(form);
        attachCalendar(form);
        applyCalendarSelection(form);
    }

    function attachMonthNav(form) {
        var widget = getCalendarWidget(form);
        if (!widget) return;

        var prevBtn = widget.querySelector('[data-cb-month-prev]');
        var nextBtn = widget.querySelector('[data-cb-month-next]');

        if (prevBtn && prevBtn.getAttribute('data-cb-nav-attached') !== '1') {
            prevBtn.setAttribute('data-cb-nav-attached', '1');
            prevBtn.addEventListener('click', function () {
                var state = getCalendarState(form);
                if (state.visibleStartIndex <= 0) return;
                state.visibleStartIndex -= 1;
                renderCalendarViewport(form);
            });
        }

        if (nextBtn && nextBtn.getAttribute('data-cb-nav-attached') !== '1') {
            nextBtn.setAttribute('data-cb-nav-attached', '1');
            nextBtn.addEventListener('click', function () {
                var state = getCalendarState(form);
                if (state.visibleStartIndex + state.visibleCount >= state.monthKeys.length) return;
                state.visibleStartIndex += 1;
                renderCalendarViewport(form);
            });
        }
    }

    function attachBookNowHandler(form) {
        var widget = getCalendarWidget(form);
        if (!widget) return;

        var button = widget.querySelector('[data-cb-book-now]');
        if (!button || button.getAttribute('data-cb-book-attached') === '1') return;

        button.setAttribute('data-cb-book-attached', '1');
        button.addEventListener('click', function () {
            applyCalendarSelection(form);
            var url = buildIbeUrl(form);
            if (url) openIbeUrl(form, url);
        });
    }

    function scheduleCalendarRefetch(form) {
        var state = getCalendarState(form);
        if (state.refetchTimer) clearTimeout(state.refetchTimer);
        state.refetchTimer = setTimeout(function () {
            fetchCalendar(form);
        }, 400);
    }

    function syncStepperDisplay(block, field) {
        var input = block.querySelector('[data-cb-field="' + field + '"]');
        var stepper = block.querySelector('[data-cb-stepper="' + field + '"]');
        if (!input || !stepper) return;
        var valueEl = stepper.querySelector('[data-cb-stepper-value]');
        if (valueEl) valueEl.textContent = input.value;
    }

    function getMaxOccupancy(form) {
        if (!form) return 0;
        return clamp(form.getAttribute('data-cb-max-occupancy') || '0', 0, 99);
    }

    function getCalendarDefaultOccupancy(form) {
        return getFormDefaultOccupancy(form);
    }

    function getRoomGuestTotal(block) {
        var adultsEl = block.querySelector('[data-cb-field="adults"]');
        var childrenEl = block.querySelector('[data-cb-field="children-count"]');
        var adults = adultsEl ? clamp(adultsEl.value, 1, 99) : 1;
        var children = childrenEl ? clamp(childrenEl.value, 0, 99) : 0;
        return adults + children;
    }

    function clampRoomToMax(block, maxOccupancy) {
        if (maxOccupancy <= 0 || !block) return;
        var adultsEl = block.querySelector('[data-cb-field="adults"]');
        var childrenEl = block.querySelector('[data-cb-field="children-count"]');
        if (!adultsEl || !childrenEl) return;

        var adults = clamp(adultsEl.value, 1, 99);
        var children = clamp(childrenEl.value, 0, 99);
        while (adults + children > maxOccupancy) {
            if (children > 0) {
                children--;
            } else if (adults > 1) {
                adults--;
            } else {
                break;
            }
        }

        adultsEl.value = String(adults);
        childrenEl.value = String(children);
        syncStepperDisplay(block, 'adults');
        syncStepperDisplay(block, 'children-count');
        syncChildAgeInputs(block);
    }

    function canIncreaseGuests(block, field, delta, maxOccupancy) {
        if (delta <= 0 || maxOccupancy <= 0) return true;
        var adultsEl = block.querySelector('[data-cb-field="adults"]');
        var childrenEl = block.querySelector('[data-cb-field="children-count"]');
        var adults = adultsEl ? clamp(adultsEl.value, 1, 99) : 1;
        var children = childrenEl ? clamp(childrenEl.value, 0, 99) : 0;

        if (field === 'adults') {
            adults += delta;
        } else if (field === 'children-count') {
            children += delta;
        }

        return adults + children <= maxOccupancy;
    }

    function updateStepperButtonStates(form) {
        var maxOccupancy = getMaxOccupancy(form);
        var widget = getCalendarWidget(form);
        if (!widget) return;

        widget.querySelectorAll('[data-cb-room-block]').forEach(function (block) {
            ['adults', 'children-count'].forEach(function (field) {
                var stepper = block.querySelector('[data-cb-stepper="' + field + '"]');
                if (!stepper) return;
                var input = block.querySelector('[data-cb-field="' + field + '"]');
                var minusBtn = stepper.querySelector('[data-cb-step="-1"]');
                var plusBtn = stepper.querySelector('[data-cb-step="1"]');
                var min = field === 'adults' ? 1 : 0;
                var absMax = 10;

                if (input && minusBtn) {
                    minusBtn.disabled = clamp(input.value, min, absMax) <= min;
                    minusBtn.classList.toggle('cb-stepper__btn--disabled', minusBtn.disabled);
                }
                if (input && plusBtn) {
                    var canPlus = clamp(input.value, min, absMax) < absMax
                        && (maxOccupancy <= 0 || canIncreaseGuests(block, field, 1, maxOccupancy));
                    plusBtn.disabled = !canPlus;
                    plusBtn.classList.toggle('cb-stepper__btn--disabled', !canPlus);
                }
            });
        });
    }

    function syncDetailRoomCount(form) {
        var list = form.querySelector('[data-cb-rooms-list]');
        var countEl = form.querySelector('[data-cb-field="rooms-count"]');
        if (!list || !countEl) return;
        countEl.value = String(list.querySelectorAll('[data-cb-room-block]').length);
    }

    function updateAddRoomVisibility(form) {
        var btn = form.querySelector('[data-cb-add-room]');
        if (!btn) return;
        var list = form.querySelector('[data-cb-rooms-list]');
        var blockCount = list ? list.querySelectorAll('[data-cb-room-block]').length : 1;
        btn.removeAttribute('hidden');
        btn.disabled = blockCount >= MAX_ROOMS;
    }

    function buildCompactStepper(field, value, adultsLabel, childrenLabel) {
        var isAdults = field === 'adults';
        var labelText = isAdults ? adultsLabel : childrenLabel;
        return '<div class="cb-stepper" data-cb-stepper="' + (isAdults ? 'adults' : 'children-count') + '">'
            + '<span class="cb-stepper__label">' + labelText + '</span>'
            + '<span class="cb-stepper__control">'
            + '<button type="button" class="cb-stepper__btn" data-cb-step="-1" aria-label="-">−</button>'
            + '<span class="cb-stepper__value" data-cb-stepper-value>' + value + '</span>'
            + '<button type="button" class="cb-stepper__btn" data-cb-step="1" aria-label="+">+</button>'
            + '</span></div>';
    }

    function createCompactCalendarRoomBlock(index, defaults) {
        defaults = defaults || { adults: 1, children: 0, ages: [] };
        var adults = defaults.adults || 1;
        var children = defaults.children || 0;
        var ages = Array.isArray(defaults.ages) ? defaults.ages.slice(0, children) : [];
        while (ages.length < children) {
            ages.push(12);
        }
        var agesString = ages.join(', ');
        var block = document.createElement('fieldset');
        block.className = 'cb-room-block cb-room-block--compact';
        block.setAttribute('data-cb-room-block', '');
        block.setAttribute('data-cb-room-index', String(index));

        var adultsLabel = cbText('adults', 'Adults');
        var childrenLabel = cbText('children', 'Children');
        var headerHtml = '';
        if (index > 0) {
            headerHtml = '<div class="cb-room-block__header">'
                + '<legend class="cb-room-block__title">'
                + label(window.CB_LABELS && window.CB_LABELS.roomN, index + 1)
                + '</legend>'
                + '<button type="button" class="cb-room-block__remove" data-cb-remove-room="">'
                + cbText('calendarRemoveRoom', 'Remove room')
                + '</button></div>';
        }

        block.innerHTML = headerHtml
            + '<input type="hidden" name="rooms[' + index + '][adults]" value="' + adults + '" data-cb-field="adults" />'
            + '<input type="hidden" name="rooms[' + index + '][children]" value="' + children + '" data-cb-field="children-count" />'
            + '<input type="hidden" name="rooms[' + index + '][childrenAges]" value="' + agesString + '" data-cb-children-ages-hidden />'
            + buildCompactStepper('adults', adults, adultsLabel, childrenLabel)
            + buildCompactStepper('children-count', children, adultsLabel, childrenLabel)
            + '<div class="cb-field cb-field--ages" data-cb-ages-container' + (children > 0 ? '' : ' hidden="hidden"') + '>'
            + '<span class="cb-field__label cb-field__label--group">' + cbText('childrenAges', 'Children ages') + '</span>'
            + '<div class="cb-ages" data-cb-ages-list></div></div>';

        return block;
    }

    function reindexDetailRoomBlocks(form) {
        var list = form.querySelector('[data-cb-rooms-list]');
        if (!list) return;

        var blocks = Array.prototype.slice.call(list.querySelectorAll('[data-cb-room-block]'));
        blocks.forEach(function (block, index) {
            block.setAttribute('data-cb-room-index', String(index));
            block.querySelector('[data-cb-field="adults"]').name = 'rooms[' + index + '][adults]';
            block.querySelector('[data-cb-field="children-count"]').name = 'rooms[' + index + '][children]';
            block.querySelector('[data-cb-children-ages-hidden]').name = 'rooms[' + index + '][childrenAges]';
            block.querySelectorAll('[data-cb-child-age]').forEach(function (input) {
                input.name = 'rooms[' + index + '][childAge][]';
            });

            var header = block.querySelector('.cb-room-block__header');
            if (index === 0 && header) {
                header.parentNode.removeChild(header);
            } else if (index > 0) {
                if (!header) {
                    var newHeader = document.createElement('div');
                    newHeader.className = 'cb-room-block__header';
                    newHeader.innerHTML = '<legend class="cb-room-block__title"></legend>'
                        + '<button type="button" class="cb-room-block__remove" data-cb-remove-room="">'
                        + (window.CB_LABELS && window.CB_LABELS.calendarRemoveRoom || 'Remove room')
                        + '</button>';
                    block.insertBefore(newHeader, block.firstChild);
                    header = newHeader;
                }
                var title = header.querySelector('.cb-room-block__title');
                if (title) {
                    title.textContent = label(window.CB_LABELS && window.CB_LABELS.roomN, index + 1);
                }
            }
        });

        syncDetailRoomCount(form);
    }

    function attachRemoveRoomButton(block, form) {
        if (!block) return;
        var btn = block.querySelector('[data-cb-remove-room]');
        if (!btn || btn.getAttribute('data-cb-remove-attached') === '1') return;
        btn.setAttribute('data-cb-remove-attached', '1');
        btn.addEventListener('click', function () {
            var list = form.querySelector('[data-cb-rooms-list]');
            if (!list || !block.parentNode) return;
            block.parentNode.removeChild(block);
            reindexDetailRoomBlocks(form);
            form.querySelectorAll('[data-cb-room-block]').forEach(function (remainingBlock) {
                attachRemoveRoomButton(remainingBlock, form);
            });
            attachCalendarOccupancy(form);
            updateStepperButtonStates(form);
            updateAddRoomVisibility(form);
            scheduleCalendarRefetch(form);
            scheduleOffersRefetch(form);
        });
    }

    function attachDetailAddRoom(form) {
        if (getMaxOccupancy(form) <= 0) return;

        var addBtn = form.querySelector('[data-cb-add-room]');
        if (addBtn && addBtn.getAttribute('data-cb-add-room-attached') !== '1') {
            addBtn.setAttribute('data-cb-add-room-attached', '1');
            addBtn.addEventListener('click', function () {
                var list = form.querySelector('[data-cb-rooms-list]');
                if (!list) return;
                var blocks = list.querySelectorAll('[data-cb-room-block]');
                if (blocks.length >= MAX_ROOMS) return;

                var block = createCompactCalendarRoomBlock(blocks.length, getCalendarDefaultOccupancy(form));
                list.appendChild(block);
                syncChildAgeInputs(block);
                attachRemoveRoomButton(block, form);
                attachCalendarOccupancy(form);
                clampRoomToMax(block, getMaxOccupancy(form));
                updateStepperButtonStates(form);
                updateAddRoomVisibility(form);
                syncDetailRoomCount(form);
                scheduleCalendarRefetch(form);
                scheduleOffersRefetch(form);
            });
        }

        form.querySelectorAll('[data-cb-room-block]').forEach(function (block) {
            attachRemoveRoomButton(block, form);
            clampRoomToMax(block, getMaxOccupancy(form));
        });
        updateStepperButtonStates(form);
        updateAddRoomVisibility(form);
    }

    function attachCalendarOccupancy(form) {
        var widget = getCalendarWidget(form);
        if (!widget) return;

        var maxOccupancy = getMaxOccupancy(form);

        widget.querySelectorAll('[data-cb-stepper]').forEach(function (stepper) {
            if (stepper.getAttribute('data-cb-stepper-attached') === '1') return;
            stepper.setAttribute('data-cb-stepper-attached', '1');

            var block = stepper.closest('[data-cb-room-block]');
            var field = stepper.getAttribute('data-cb-stepper');
            var input = block ? block.querySelector('[data-cb-field="' + field + '"]') : null;
            if (!block || !input) return;

            stepper.querySelectorAll('[data-cb-step]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var delta = parseInt(btn.getAttribute('data-cb-step') || '0', 10);
                    var min = field === 'adults' ? 1 : 0;
                    var max = 10;
                    if (maxOccupancy > 0 && delta > 0 && !canIncreaseGuests(block, field, delta, maxOccupancy)) {
                        updateStepperButtonStates(form);
                        updateAddRoomVisibility(form);
                        return;
                    }
                    var next = clamp(parseInt(input.value, 10) + delta, min, max);
                    input.value = String(next);
                    if (maxOccupancy > 0) {
                        clampRoomToMax(block, maxOccupancy);
                    }
                    syncStepperDisplay(block, field);
                    if (field === 'children-count') syncChildAgeInputs(block);
                    updateStepperButtonStates(form);
                    updateAddRoomVisibility(form);
                    scheduleCalendarRefetch(form);
                    scheduleOffersRefetch(form);
                });
            });
        });

        form.querySelectorAll('[data-cb-room-block] [data-cb-field="adults"], [data-cb-field="children-count"]').forEach(function (input) {
            if (input.getAttribute('data-cb-refetch-attached') === '1') return;
            input.setAttribute('data-cb-refetch-attached', '1');
            input.addEventListener('change', function () {
                var block = input.closest('[data-cb-room-block]');
                if (block && maxOccupancy > 0) {
                    clampRoomToMax(block, maxOccupancy);
                    updateStepperButtonStates(form);
                    updateAddRoomVisibility(form);
                }
                scheduleCalendarRefetch(form);
                scheduleOffersRefetch(form);
            });
        });

        if (form.querySelector('[data-cb-field="rooms-count"]')) {
            var roomsCountEl = form.querySelector('[data-cb-field="rooms-count"]');
            if (roomsCountEl.getAttribute('data-cb-refetch-attached') !== '1') {
                roomsCountEl.setAttribute('data-cb-refetch-attached', '1');
                roomsCountEl.addEventListener('change', function () {
                    scheduleCalendarRefetch(form);
                    scheduleOffersRefetch(form);
                });
            }
        }
    }

    function initCalendarSelection(form) {
        var state = getCalendarState(form);
        state.selectedFrom = null;
        state.selectedTo = null;
        state.nextPick = 'from';
        state.fromMinStay = 1;
        state.visibleCount = resolveVisibleMonthCount(form);
        state.visibleStartIndex = 0;

        attachBookNowHandler(form);
        attachMonthNav(form);
        attachCalendarOccupancy(form);
        attachDetailAddRoom(form);
        applyCalendarSelection(form);
    }

    function fetchCalendar(form) {
        var widget = getCalendarWidget(form);
        var host = widget ? widget.querySelector('[data-cb-calendar-host]') : null;
        var url = form.getAttribute('data-cb-fetch-url');
        if (!host || !url) return Promise.reject(new Error('Missing calendar host'));

        var state = getCalendarState(form);
        var preserveFrom = state.selectedFrom;
        var preserveTo = state.selectedTo;

        host.removeAttribute('hidden');
        host.innerHTML = '<p class="cb-calendar__status">' + (
            window.CB_LABELS && window.CB_LABELS.calendarLoading
                ? window.CB_LABELS.calendarLoading
                : 'Loading availability…'
        ) + '</p>';

        form.querySelectorAll('[data-cb-room-block]').forEach(syncHiddenChildrenAges);

        var params = new URLSearchParams(serializeCalendarRequest(form));
        var separator = url.indexOf('?') >= 0 ? '&' : '?';

        return fetch(url + separator + params.toString(), {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        }).then(function (response) {
            return response.text().then(function (body) {
                var payload = null;
                if (body) {
                    try {
                        payload = JSON.parse(body);
                    } catch (parseError) {
                        console.warn('[CASABLANCA] Non-JSON response', response.status, body.slice(0, 200));
                        throw new Error('HTTP ' + response.status);
                    }
                }

                if (!response.ok) {
                    var httpMessage = payload && (payload.message || payload.error)
                        ? payload.message || payload.error
                        : 'HTTP ' + response.status;
                    throw new Error(httpMessage);
                }

                return payload || {};
            });
        }).then(function (payload) {
            if (payload.error) throw new Error(payload.message || payload.error);

            state.months = payload.months || {};
            state.monthKeys = Object.keys(state.months).sort();
            state.dayMap = buildDayMap(state.months);
            state.visibleCount = resolveVisibleMonthCount(form);
            if (state.visibleStartIndex + state.visibleCount > state.monthKeys.length) {
                state.visibleStartIndex = Math.max(0, state.monthKeys.length - state.visibleCount);
            }

            state.selectedFrom = preserveFrom && state.dayMap[preserveFrom] ? preserveFrom : null;
            state.selectedTo = preserveTo && state.selectedFrom && preserveTo > state.selectedFrom
                ? preserveTo
                : null;
            state.nextPick = state.selectedFrom && !state.selectedTo ? 'to' : 'from';

            renderCalendarViewport(form);
        }).catch(function (error) {
            var fallback = window.CB_LABELS && window.CB_LABELS.calendarError
                ? window.CB_LABELS.calendarError
                : 'Could not load availability.';
            var message = error && error.message && error.message !== 'HTTP undefined'
                ? error.message
                : fallback;
            host.innerHTML = '<p class="cb-calendar__status cb-calendar__status--error" role="alert">' + message + '</p>';
            console.warn('[CASABLANCA]', error);
        });
    }

    function attachCalendar(form) {
        var widget = getCalendarWidget(form);
        if (!widget) return;

        widget.querySelectorAll('.cb-calendar__cell[data-cb-date]').forEach(function (cell) {
            if (cell.getAttribute('data-cb-is-past') === '1') return;

            var handler = function () {
                handleCalendarCellClick(form, cell);
            };

            cell.onclick = handler;
            cell.onkeydown = function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    handler();
                }
            };
        });
    }

    function usesDetailCalendarOccupancy(form) {
        return getMaxOccupancy(form) > 0;
    }

    function attachOccupancyToggle(form) {
        var toggle = form.querySelector('[data-cb-occupancy-toggle]');
        var panel = form.querySelector('[data-cb-occupancy-panel]');
        if (!toggle || !panel) {
            return;
        }

        function setOpen(open) {
            if (open) {
                panel.removeAttribute('hidden');
            } else {
                panel.setAttribute('hidden', 'hidden');
            }
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        toggle.addEventListener('click', function () {
            setOpen(panel.hasAttribute('hidden'));
        });

        document.addEventListener('click', function (event) {
            if (panel.hasAttribute('hidden')) {
                return;
            }
            if (form.contains(event.target)) {
                return;
            }
            setOpen(false);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !panel.hasAttribute('hidden')) {
                setOpen(false);
                toggle.focus();
            }
        });
    }

    function attachForm(form) {
        var mode = form.getAttribute('data-cb-mode') || 'search';
        var detailOccupancy = usesDetailCalendarOccupancy(form);

        var roomsCountEl = form.querySelector('[data-cb-field="rooms-count"]');
        if (roomsCountEl && !detailOccupancy) {
            roomsCountEl.addEventListener('focus', function () {
                roomsCountEl.select();
            });
            roomsCountEl.addEventListener('input', function () {
                syncRoomBlocks(form);
            });
            roomsCountEl.addEventListener('change', function () {
                syncRoomBlocks(form, { finalize: true });
            });
        }

        form.querySelectorAll('[data-cb-room-block]').forEach(attachRoomBlock);
        if (!detailOccupancy) {
            syncRoomBlocks(form, { finalize: true });
        }

        attachOccupancyToggle(form);

        if (mode === 'calendar') {
            initCalendarSelection(form);
            fetchCalendar(form);
        }

        form.addEventListener('submit', function (e) {
            if (roomsCountEl && !detailOccupancy) {
                syncRoomBlocks(form, { finalize: true });
            }
            form.querySelectorAll('[data-cb-room-block]').forEach(syncHiddenChildrenAges);

            if (mode === 'calendar') {
                e.preventDefault();
                e.stopPropagation();
                return;
            }

            var url = buildIbeUrl(form);
            if (url) {
                e.preventDefault();
                e.stopPropagation();
                openIbeUrl(form, url);
            }
        }, false);

        var arrivalEl = form.querySelector('[data-cb-field="arrival"]');
        var departureEl = form.querySelector('[data-cb-field="departure"]');
        if (arrivalEl && departureEl && mode === 'search') {
            function syncSearchBarDepartureBounds() {
                var minDep = defaultDeparture(arrivalEl.value, 1);
                if (!minDep) {
                    return;
                }
                departureEl.min = minDep;
                if (
                    !departureEl.value
                    || departureEl.value <= arrivalEl.value
                    || departureEl.value < minDep
                ) {
                    departureEl.value = defaultDeparture(arrivalEl.value, 7);
                }
            }

            syncSearchBarDepartureBounds();
            arrivalEl.addEventListener('change', syncSearchBarDepartureBounds);
            departureEl.addEventListener('change', function () {
                var minDep = defaultDeparture(arrivalEl.value, 1);
                if (
                    !minDep
                    || departureEl.value <= arrivalEl.value
                    || departureEl.value < minDep
                ) {
                    departureEl.value = minDep;
                }
            });
        }
    }

    function attachBookButtons() {
        document.querySelectorAll(
            '.cb-room-card__book[data-cb-ibe-base], .cb-room-detail__book[data-cb-ibe-base],'
            + '.cb-package-card__book[data-cb-ibe-base], .cb-package-detail__book[data-cb-ibe-base]'
        ).forEach(function (button) {
            button.addEventListener('click', function (event) {
                var url = buildIbeUrl(button);
                if (url) {
                    event.preventDefault();
                    openIbeUrl(button, url);
                }
            });
        });
    }

    function attachDescriptionToggles() {
        document.querySelectorAll('[data-cb-description-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                var wrapper = button.closest('.cb-overview-description');
                if (!wrapper) {
                    return;
                }

                var preview = wrapper.querySelector('.cb-overview-description__preview');
                var full = wrapper.querySelector('.cb-overview-description__full');
                var expanded = button.getAttribute('aria-expanded') === 'true';
                var labels = window.CB_LABELS || {};

                if (expanded) {
                    if (preview) {
                        preview.hidden = false;
                    }
                    if (full) {
                        full.hidden = true;
                    }
                    button.setAttribute('aria-expanded', 'false');
                    button.textContent = labels.more || 'More';
                } else {
                    if (preview) {
                        preview.hidden = true;
                    }
                    if (full) {
                        full.hidden = false;
                    }
                    button.setAttribute('aria-expanded', 'true');
                    button.textContent = labels.less || 'Less';
                }
            });
        });
    }

    function showCarouselSlide(carousel, index) {
        var slides = carousel.querySelectorAll('[data-cb-carousel-slide]');
        var dots = carousel.querySelectorAll('[data-cb-carousel-dot]');
        var total = slides.length;
        if (total === 0) {
            return;
        }

        var nextIndex = ((index % total) + total) % total;
        slides.forEach(function (slide, slideIndex) {
            var active = slideIndex === nextIndex;
            slide.hidden = !active;
            slide.classList.toggle('cb-image-carousel__slide--active', active);
        });
        dots.forEach(function (dot, dotIndex) {
            var active = dotIndex === nextIndex;
            dot.classList.toggle('cb-image-carousel__dot--active', active);
            dot.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        carousel.setAttribute('data-cb-carousel-index', String(nextIndex));
    }

    function attachCarousels() {
        document.querySelectorAll('[data-cb-carousel]').forEach(function (carousel) {
            var currentIndex = parseInt(carousel.getAttribute('data-cb-carousel-index') || '0', 10);
            showCarouselSlide(carousel, currentIndex);

            var prev = carousel.querySelector('[data-cb-carousel-prev]');
            var next = carousel.querySelector('[data-cb-carousel-next]');
            if (prev) {
                prev.addEventListener('click', function () {
                    var index = parseInt(carousel.getAttribute('data-cb-carousel-index') || '0', 10);
                    showCarouselSlide(carousel, index - 1);
                });
            }
            if (next) {
                next.addEventListener('click', function () {
                    var index = parseInt(carousel.getAttribute('data-cb-carousel-index') || '0', 10);
                    showCarouselSlide(carousel, index + 1);
                });
            }

            carousel.querySelectorAll('[data-cb-carousel-dot]').forEach(function (dot) {
                dot.addEventListener('click', function () {
                    showCarouselSlide(
                        carousel,
                        parseInt(dot.getAttribute('data-cb-carousel-dot') || '0', 10)
                    );
                });
            });

            carousel.addEventListener('keydown', function (event) {
                var index = parseInt(carousel.getAttribute('data-cb-carousel-index') || '0', 10);
                if (event.key === 'ArrowLeft') {
                    event.preventDefault();
                    showCarouselSlide(carousel, index - 1);
                } else if (event.key === 'ArrowRight') {
                    event.preventDefault();
                    showCarouselSlide(carousel, index + 1);
                }
            });
        });
    }

    function bindCalendarResize() {
        if (window.__cbCalendarResizeBound) {
            return;
        }
        window.__cbCalendarResizeBound = true;

        var resizeTimer = null;
        window.addEventListener('resize', function () {
            if (resizeTimer) {
                clearTimeout(resizeTimer);
            }
            resizeTimer = setTimeout(function () {
                document.querySelectorAll('.cb-widget__form[data-cb-mode="calendar"]').forEach(function (form) {
                    var state = getCalendarState(form);
                    var nextCount = resolveVisibleMonthCount(form);
                    if (nextCount === state.visibleCount) {
                        return;
                    }

                    state.visibleCount = nextCount;
                    if (state.visibleStartIndex + state.visibleCount > state.monthKeys.length) {
                        state.visibleStartIndex = Math.max(0, state.monthKeys.length - state.visibleCount);
                    }
                    renderCalendarViewport(form);
                });
            }, 150);
        });
    }

    function init() {
        if (window.__cbWidgetInit) {
            return;
        }
        window.__cbWidgetInit = true;

        bindCalendarResize();
        document.querySelectorAll('.cb-widget__form').forEach(attachForm);
        attachBookButtons();
        attachDescriptionToggles();
        attachCarousels();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
