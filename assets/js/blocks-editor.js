/**
 * Gutenberg editor scripts for CASABLANCA dynamic blocks.
 * Plain JS (no build step) — registered from BlockRegistrar.
 */
(function (wp) {
	'use strict';

	if (!wp || !wp.blocks || !wp.element) {
		return;
	}

	var registerBlockType = wp.blocks.registerBlockType;
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;
	var RangeControl = wp.components.RangeControl;
	var SelectControl = wp.components.SelectControl;
	var Placeholder = wp.components.Placeholder;
	var Spinner = wp.components.Spinner;
	var ServerSideRender = wp.serverSideRender;
	var __ = wp.i18n.__;
	var apiFetch = wp.apiFetch;

	function Preview(props) {
		var blockProps = useBlockProps({ className: 'cb-block-editor-preview' });

		if (!ServerSideRender) {
			return el(
				'div',
				blockProps,
				el(Placeholder, {
					label: props.title,
					instructions: __('Preview unavailable — enable ServerSideRender.', 'casablanca-booking'),
				})
			);
		}

		return el(
			'div',
			blockProps,
			el(ServerSideRender, {
				block: props.name,
				attributes: props.attributes,
				EmptyResponsePlaceholder: function () {
					return el(Placeholder, {
						icon: 'calendar-alt',
						label: props.title,
						instructions: __(
							'No preview yet. Save a CASABLANCA configuration and sync, then refresh the editor.',
							'casablanca-booking'
						),
					});
				},
				LoadingResponsePlaceholder: function () {
					return el(Placeholder, { label: props.title }, el(Spinner));
				},
			})
		);
	}

	function appearanceControl(attributes, setAttributes) {
		return el(SelectControl, {
			label: __('Appearance', 'casablanca-booking'),
			value: attributes.appearance || 'default',
			options: [
				{ label: __('Default', 'casablanca-booking'), value: 'default' },
				{ label: __('Inherit theme', 'casablanca-booking'), value: 'inherit' },
				{ label: __('Compact', 'casablanca-booking'), value: 'compact' },
			],
			onChange: function (value) {
				setAttributes({ appearance: value });
			},
		});
	}

	function languageControl(attributes, setAttributes) {
		return el(SelectControl, {
			label: __('Language', 'casablanca-booking'),
			value: attributes.language || '',
			options: [
				{ label: __('Auto', 'casablanca-booking'), value: '' },
				{ label: 'Deutsch', value: 'de' },
				{ label: 'English', value: 'en' },
				{ label: 'Italiano', value: 'it' },
				{ label: 'Français', value: 'fr' },
			],
			onChange: function (value) {
				setAttributes({ language: value });
			},
		});
	}

	function ibeTargetControl(attributes, setAttributes) {
		return el(SelectControl, {
			label: __('IBE link target', 'casablanca-booking'),
			value: attributes.ibeLinkTarget || '_self',
			options: [
				{ label: __('Same tab (_self)', 'casablanca-booking'), value: '_self' },
				{ label: __('New tab (_blank)', 'casablanca-booking'), value: '_blank' },
			],
			onChange: function (value) {
				setAttributes({ ibeLinkTarget: value });
			},
		});
	}

	function labelControls(attributes, setAttributes) {
		var labels = attributes.labels || {};
		function setLabel(key, value) {
			var next = Object.assign({}, labels);
			next[key] = value;
			setAttributes({ labels: next });
		}
		return [
			el(TextControl, {
				key: 'heading',
				label: __('Heading label', 'casablanca-booking'),
				value: labels.heading || '',
				onChange: function (value) {
					setLabel('heading', value);
				},
			}),
			el(TextControl, {
				key: 'book',
				label: __('Book button label', 'casablanca-booking'),
				value: labels.book || '',
				onChange: function (value) {
					setLabel('book', value);
				},
			}),
			el(TextControl, {
				key: 'details',
				label: __('Details button label', 'casablanca-booking'),
				value: labels.details || '',
				onChange: function (value) {
					setLabel('details', value);
				},
			}),
		];
	}

	function DetailPageControl(props) {
		var attributes = props.attributes;
		var setAttributes = props.setAttributes;
		var state = useState([]);
		var pages = state[0];
		var setPages = state[1];
		var loadingState = useState(true);
		var loading = loadingState[0];
		var setLoading = loadingState[1];

		useEffect(function () {
			if (!apiFetch) {
				setLoading(false);
				return;
			}
			apiFetch({ path: '/wp/v2/pages?per_page=100&orderby=title&order=asc&_fields=id,title' })
				.then(function (result) {
					setPages(Array.isArray(result) ? result : []);
					setLoading(false);
				})
				.catch(function () {
					setLoading(false);
				});
		}, []);

		if (!apiFetch || (!loading && pages.length === 0)) {
			return el(TextControl, {
				label: __('Detail page ID', 'casablanca-booking'),
				help: __(
					'WordPress page ID for room/package details. Links append /{slug}/ to that page.',
					'casablanca-booking'
				),
				type: 'number',
				value: attributes.detailPageId || 0,
				onChange: function (value) {
					setAttributes({ detailPageId: parseInt(value, 10) || 0 });
				},
			});
		}

		var options = [{ label: __('— Select detail page —', 'casablanca-booking'), value: '0' }].concat(
			pages.map(function (page) {
				var title =
					page.title && page.title.rendered ? page.title.rendered.replace(/<[^>]+>/g, '') : '#' + page.id;
				return { label: title + ' (#' + page.id + ')', value: String(page.id) };
			})
		);

		return el(SelectControl, {
			label: __('Detail page', 'casablanca-booking'),
			help: __('Card “Details” links go to this page with /{slug}/ appended.', 'casablanca-booking'),
			value: String(attributes.detailPageId || 0),
			options: options,
			onChange: function (value) {
				setAttributes({ detailPageId: parseInt(value, 10) || 0 });
			},
		});
	}

	function displayToggles(attributes, setAttributes) {
		return [
			el(ToggleControl, {
				key: 'showName',
				label: __('Show name', 'casablanca-booking'),
				checked: attributes.showName !== false,
				onChange: function (value) {
					setAttributes({ showName: value });
				},
			}),
			el(ToggleControl, {
				key: 'showDescription',
				label: __('Show description', 'casablanca-booking'),
				checked: attributes.showDescription !== false,
				onChange: function (value) {
					setAttributes({ showDescription: value });
				},
			}),
			el(ToggleControl, {
				key: 'showPrice',
				label: __('Show price', 'casablanca-booking'),
				checked: attributes.showPrice !== false,
				onChange: function (value) {
					setAttributes({ showPrice: value });
				},
			}),
			el(ToggleControl, {
				key: 'showImage',
				label: __('Show image', 'casablanca-booking'),
				checked: attributes.showImage !== false,
				onChange: function (value) {
					setAttributes({ showImage: value });
				},
			}),
		];
	}

	function overviewDescriptionControls(attributes, setAttributes) {
		return [
			el(SelectControl, {
				key: 'overviewDescriptionMode',
				label: __('Overview description', 'casablanca-booking'),
				value: attributes.overviewDescriptionMode || 'teaser',
				options: [
					{ label: __('Teaser', 'casablanca-booking'), value: 'teaser' },
					{ label: __('Full (expandable)', 'casablanca-booking'), value: 'full' },
				],
				onChange: function (value) {
					setAttributes({ overviewDescriptionMode: value });
				},
			}),
			el(RangeControl, {
				key: 'overviewDescriptionLimit',
				label: __('Description teaser limit', 'casablanca-booking'),
				value: attributes.overviewDescriptionLimit || 250,
				min: 50,
				max: 2000,
				step: 10,
				onChange: function (value) {
					setAttributes({ overviewDescriptionLimit: value });
				},
			}),
		];
	}

	function detailCalendarControls(attributes, setAttributes) {
		return [
			el(ToggleControl, {
				key: 'showDetailCalendar',
				label: __('Show availability calendar', 'casablanca-booking'),
				checked: !!attributes.showDetailCalendar,
				onChange: function (value) {
					setAttributes({ showDetailCalendar: value });
				},
			}),
			el(SelectControl, {
				key: 'detailCalendarPosition',
				label: __('Calendar position', 'casablanca-booking'),
				value: attributes.detailCalendarPosition || 'below',
				options: [
					{ label: __('Above content', 'casablanca-booking'), value: 'above' },
					{ label: __('Below content', 'casablanca-booking'), value: 'below' },
				],
				onChange: function (value) {
					setAttributes({ detailCalendarPosition: value });
				},
			}),
			el(SelectControl, {
				key: 'detailCalendarInitialMonths',
				label: __('Initial months', 'casablanca-booking'),
				value: String(attributes.detailCalendarInitialMonths || 1),
				options: [
					{ label: __('1 month', 'casablanca-booking'), value: '1' },
					{ label: __('2 months', 'casablanca-booking'), value: '2' },
				],
				onChange: function (value) {
					setAttributes({ detailCalendarInitialMonths: parseInt(value, 10) || 1 });
				},
			}),
			el(SelectControl, {
				key: 'detailCalendarOfferMode',
				label: __('Calendar offer mode', 'casablanca-booking'),
				value: attributes.detailCalendarOfferMode || 'rates_only',
				help: __(
					'After selecting dates, show matching rates and/or packages in the sidebar.',
					'casablanca-booking'
				),
				options: [
					{ label: __('None', 'casablanca-booking'), value: 'none' },
					{ label: __('Rates only', 'casablanca-booking'), value: 'rates_only' },
					{ label: __('Packages only', 'casablanca-booking'), value: 'packages_only' },
					{ label: __('Rates and packages', 'casablanca-booking'), value: 'packages_and_rates' },
				],
				onChange: function (value) {
					setAttributes({ detailCalendarOfferMode: value });
				},
			}),
		];
	}

	function searchBarLabelControls(attributes, setAttributes) {
		var labels = attributes.labels || {};
		function setLabel(key, value) {
			var next = Object.assign({}, labels);
			next[key] = value;
			setAttributes({ labels: next });
		}
		return [
			el(TextControl, {
				key: 'heading',
				label: __('Heading label', 'casablanca-booking'),
				value: labels.heading || '',
				onChange: function (value) {
					setLabel('heading', value);
				},
			}),
			el(TextControl, {
				key: 'submit',
				label: __('Submit button label', 'casablanca-booking'),
				value: labels.submit || '',
				onChange: function (value) {
					setLabel('submit', value);
				},
			}),
		];
	}

	function calendarLabelControls(attributes, setAttributes) {
		var labels = attributes.labels || {};
		function setLabel(key, value) {
			var next = Object.assign({}, labels);
			next[key] = value;
			setAttributes({ labels: next });
		}
		return [
			el(TextControl, {
				key: 'heading',
				label: __('Heading label', 'casablanca-booking'),
				value: labels.heading || '',
				onChange: function (value) {
					setLabel('heading', value);
				},
			}),
			el(TextControl, {
				key: 'bookNow',
				label: __('Book now label', 'casablanca-booking'),
				value: labels.bookNow || '',
				onChange: function (value) {
					setLabel('bookNow', value);
				},
			}),
			el(TextControl, {
				key: 'enquiry',
				label: __('Enquiry button label', 'casablanca-booking'),
				value: labels.enquiry || '',
				onChange: function (value) {
					setLabel('enquiry', value);
				},
			}),
		];
	}

	function RoomCategoryControl(props) {
		var attributes = props.attributes;
		var setAttributes = props.setAttributes;
		var state = useState([]);
		var rooms = state[0];
		var setRooms = state[1];
		var loadingState = useState(true);
		var loading = loadingState[0];
		var setLoading = loadingState[1];

		useEffect(function () {
			if (!apiFetch) {
				setLoading(false);
				return;
			}
			apiFetch({ path: '/casablanca-booking/v1/catalog' })
				.then(function (result) {
					var raw = result && result.roomTypes ? result.roomTypes : {};
					var list = [];
					if (Array.isArray(raw)) {
						list = raw;
					} else if (raw && typeof raw === 'object') {
						Object.keys(raw).forEach(function (id) {
							if (id === '') {
								return;
							}
							list.push({ id: id, name: raw[id] });
						});
					}
					setRooms(list);
					setLoading(false);
				})
				.catch(function () {
					setLoading(false);
				});
		}, []);

		if (!apiFetch || (!loading && rooms.length === 0)) {
			return el(TextControl, {
				label: __('Preselect room category', 'casablanca-booking'),
				help: __('Room type ID from the synced catalog. Leave empty for none.', 'casablanca-booking'),
				value: attributes.preselectRoomCategory || '',
				onChange: function (value) {
					setAttributes({ preselectRoomCategory: value });
				},
			});
		}

		var options = [{ label: __('— None —', 'casablanca-booking'), value: '' }].concat(
			rooms.map(function (room) {
				var id = room.id || room.value || '';
				var name = room.name || room.label || id;
				return { label: name + ' (' + id + ')', value: String(id) };
			})
		);

		return el(SelectControl, {
			label: __('Preselect room category', 'casablanca-booking'),
			help: loading ? __('Loading room types…', 'casablanca-booking') : '',
			value: attributes.preselectRoomCategory || '',
			options: options,
			onChange: function (value) {
				setAttributes({ preselectRoomCategory: value });
			},
		});
	}

	function occupancyDefaultsControls(attributes, setAttributes) {
		return [
			el(RangeControl, {
				key: 'defaultRooms',
				label: __('Default rooms', 'casablanca-booking'),
				value: attributes.defaultRooms || 1,
				min: 1,
				max: 5,
				onChange: function (value) {
					setAttributes({ defaultRooms: value });
				},
			}),
			el(RangeControl, {
				key: 'defaultAdults',
				label: __('Default adults', 'casablanca-booking'),
				value: attributes.defaultAdults || 2,
				min: 1,
				max: 10,
				onChange: function (value) {
					setAttributes({ defaultAdults: value });
				},
			}),
			el(RangeControl, {
				key: 'defaultChildren',
				label: __('Default children', 'casablanca-booking'),
				value: attributes.defaultChildren || 0,
				min: 0,
				max: 10,
				onChange: function (value) {
					setAttributes({ defaultChildren: value });
				},
			}),
			el(TextControl, {
				key: 'defaultChildrenAges',
				label: __('Default children ages', 'casablanca-booking'),
				help: __('Comma-separated ages, e.g. 4, 8', 'casablanca-booking'),
				value: attributes.defaultChildrenAges || '',
				onChange: function (value) {
					setAttributes({ defaultChildrenAges: value });
				},
			}),
		];
	}

	var blockDefs = [
		{
			name: 'casablanca/search-bar',
			title: __('CASABLANCA Search Bar', 'casablanca-booking'),
			edit: function (props) {
				var attributes = props.attributes;
				var setAttributes = props.setAttributes;
				return el(
					Fragment,
					null,
					el(
						InspectorControls,
						null,
						el(
							PanelBody,
							{ title: __('Search bar', 'casablanca-booking'), initialOpen: true },
							occupancyDefaultsControls(attributes, setAttributes),
							el(ToggleControl, {
								label: __('Hide occupancy (icon toggle)', 'casablanca-booking'),
								help: __(
									'When on, rooms/adults/children open from an icon button. When off, occupancy fields are always visible.',
									'casablanca-booking'
								),
								checked: attributes.compactOccupancy !== false,
								onChange: function (value) {
									setAttributes({ compactOccupancy: value });
								},
							}),
							languageControl(attributes, setAttributes),
							ibeTargetControl(attributes, setAttributes),
							appearanceControl(attributes, setAttributes)
						),
						el(
							PanelBody,
							{ title: __('Labels', 'casablanca-booking'), initialOpen: false },
							searchBarLabelControls(attributes, setAttributes)
						)
					),
					el(Preview, { name: props.name, title: props.title || 'CASABLANCA Search Bar', attributes: attributes })
				);
			},
		},
		{
			name: 'casablanca/calendar',
			title: __('CASABLANCA Availability Calendar', 'casablanca-booking'),
			edit: function (props) {
				var attributes = props.attributes;
				var setAttributes = props.setAttributes;
				return el(
					Fragment,
					null,
					el(
						InspectorControls,
						null,
						el(
							PanelBody,
							{ title: __('Calendar', 'casablanca-booking'), initialOpen: true },
							el(RoomCategoryControl, { attributes: attributes, setAttributes: setAttributes }),
							occupancyDefaultsControls(attributes, setAttributes),
							el(RangeControl, {
								label: __('Window days', 'casablanca-booking'),
								value: attributes.windowDays || 90,
								min: 7,
								max: 180,
								onChange: function (value) {
									setAttributes({ windowDays: value });
								},
							}),
							el(SelectControl, {
								label: __('Initial months', 'casablanca-booking'),
								value: String(attributes.calendarInitialMonths || 1),
								options: [
									{ label: __('1 month', 'casablanca-booking'), value: '1' },
									{ label: __('2 months', 'casablanca-booking'), value: '2' },
								],
								onChange: function (value) {
									setAttributes({ calendarInitialMonths: parseInt(value, 10) || 1 });
								},
							}),
							el(SelectControl, {
								label: __('Offer mode', 'casablanca-booking'),
								value: attributes.calendarOfferMode || 'none',
								help: __(
									'After selecting dates, show matching rates and/or packages in the sidebar.',
									'casablanca-booking'
								),
								options: [
									{ label: __('None', 'casablanca-booking'), value: 'none' },
									{ label: __('Rates only', 'casablanca-booking'), value: 'rates_only' },
									{ label: __('Packages only', 'casablanca-booking'), value: 'packages_only' },
									{ label: __('Rates and packages', 'casablanca-booking'), value: 'packages_and_rates' },
								],
								onChange: function (value) {
									setAttributes({ calendarOfferMode: value });
								},
							}),
							languageControl(attributes, setAttributes),
							ibeTargetControl(attributes, setAttributes),
							appearanceControl(attributes, setAttributes)
						),
						el(
							PanelBody,
							{ title: __('Enquiry', 'casablanca-booking'), initialOpen: false },
							el(ToggleControl, {
								label: __('Show enquiry button', 'casablanca-booking'),
								checked: !!attributes.showEnquiryButton,
								onChange: function (value) {
									setAttributes({ showEnquiryButton: value });
								},
							}),
							el(TextControl, {
								label: __('Enquiry URL', 'casablanca-booking'),
								value: attributes.enquiryUrl || '',
								onChange: function (value) {
									setAttributes({ enquiryUrl: value });
								},
							}),
							el(SelectControl, {
								label: __('Enquiry link target', 'casablanca-booking'),
								value: attributes.enquiryLinkTarget || '_self',
								options: [
									{ label: __('Same tab (_self)', 'casablanca-booking'), value: '_self' },
									{ label: __('New tab (_blank)', 'casablanca-booking'), value: '_blank' },
								],
								onChange: function (value) {
									setAttributes({ enquiryLinkTarget: value });
								},
							})
						),
						el(
							PanelBody,
							{ title: __('Labels', 'casablanca-booking'), initialOpen: false },
							calendarLabelControls(attributes, setAttributes)
						)
					),
					el(Preview, { name: props.name, title: props.title || 'CASABLANCA Calendar', attributes: attributes })
				);
			},
		},
		{
			name: 'casablanca/room-types',
			title: __('CASABLANCA Room Types', 'casablanca-booking'),
			edit: function (props) {
				var attributes = props.attributes;
				var setAttributes = props.setAttributes;
				return el(
					Fragment,
					null,
					el(
						InspectorControls,
						null,
						el(
							PanelBody,
							{ title: __('General', 'casablanca-booking'), initialOpen: true },
							appearanceControl(attributes, setAttributes),
							el(TextControl, {
								label: __('Filter room type ID', 'casablanca-booking'),
								value: attributes.filterRoomType || '',
								help: __('Leave empty to show all rooms.', 'casablanca-booking'),
								onChange: function (value) {
									setAttributes({ filterRoomType: value });
								},
							}),
							el(RangeControl, {
								label: __('Price window (days)', 'casablanca-booking'),
								value: attributes.windowDays || 90,
								min: 7,
								max: 180,
								onChange: function (value) {
									setAttributes({ windowDays: value });
								},
							}),
							el(RangeControl, {
								label: __('Default adults', 'casablanca-booking'),
								value: attributes.defaultAdults || 2,
								min: 1,
								max: 10,
								onChange: function (value) {
									setAttributes({ defaultAdults: value });
								},
							}),
							el(RangeControl, {
								label: __('Default children', 'casablanca-booking'),
								value: attributes.defaultChildren || 0,
								min: 0,
								max: 10,
								onChange: function (value) {
									setAttributes({ defaultChildren: value });
								},
							}),
							el(RangeControl, {
								label: __('Stay nights', 'casablanca-booking'),
								value: attributes.stayNights || 7,
								min: 1,
								max: 30,
								onChange: function (value) {
									setAttributes({ stayNights: value });
								},
							}),
							languageControl(attributes, setAttributes),
							ibeTargetControl(attributes, setAttributes)
						),
						el(
							PanelBody,
							{ title: __('Display', 'casablanca-booking'), initialOpen: true },
							el(SelectControl, {
								label: __('Overview layout', 'casablanca-booking'),
								value: attributes.overviewLayout || 'grid',
								options: [
									{ label: __('Grid', 'casablanca-booking'), value: 'grid' },
									{ label: __('List', 'casablanca-booking'), value: 'list' },
								],
								onChange: function (value) {
									setAttributes({ overviewLayout: value });
								},
							}),
							el(SelectControl, {
								label: __('Card link', 'casablanca-booking'),
								value: attributes.cardLinkType || 'book',
								options: [
									{ label: __('Book (IBE)', 'casablanca-booking'), value: 'book' },
									{ label: __('Details page', 'casablanca-booking'), value: 'details' },
								],
								onChange: function (value) {
									setAttributes({ cardLinkType: value });
								},
							}),
							el(DetailPageControl, { attributes: attributes, setAttributes: setAttributes }),
							displayToggles(attributes, setAttributes),
							overviewDescriptionControls(attributes, setAttributes)
						),
						el(PanelBody, { title: __('Labels', 'casablanca-booking'), initialOpen: false }, labelControls(attributes, setAttributes))
					),
					el(Preview, { name: props.name, title: props.title || 'CASABLANCA Room Types', attributes: attributes })
				);
			},
		},
		{
			name: 'casablanca/packages',
			title: __('CASABLANCA Packages', 'casablanca-booking'),
			edit: function (props) {
				var attributes = props.attributes;
				var setAttributes = props.setAttributes;
				return el(
					Fragment,
					null,
					el(
						InspectorControls,
						null,
						el(
							PanelBody,
							{ title: __('General', 'casablanca-booking'), initialOpen: true },
							appearanceControl(attributes, setAttributes),
							el(TextControl, {
								label: __('Filter package ID', 'casablanca-booking'),
								value: attributes.filterPackage || '',
								help: __('Leave empty to show all packages.', 'casablanca-booking'),
								onChange: function (value) {
									setAttributes({ filterPackage: value });
								},
							}),
							el(RangeControl, {
								label: __('Stay nights', 'casablanca-booking'),
								value: attributes.stayNights || 7,
								min: 1,
								max: 30,
								onChange: function (value) {
									setAttributes({ stayNights: value });
								},
							}),
							el(ToggleControl, {
								label: __('Filter by stay nights', 'casablanca-booking'),
								checked: attributes.filterByStayNights !== false,
								help: __('Only packages bookable for the selected stay length.', 'casablanca-booking'),
								onChange: function (value) {
									setAttributes({ filterByStayNights: value });
								},
							}),
							el(RangeControl, {
								label: __('Price window (days)', 'casablanca-booking'),
								value: attributes.windowDays || 90,
								min: 7,
								max: 180,
								onChange: function (value) {
									setAttributes({ windowDays: value });
								},
							}),
							el(RangeControl, {
								label: __('Default adults', 'casablanca-booking'),
								value: attributes.defaultAdults || 2,
								min: 1,
								max: 10,
								onChange: function (value) {
									setAttributes({ defaultAdults: value });
								},
							}),
							languageControl(attributes, setAttributes),
							ibeTargetControl(attributes, setAttributes)
						),
						el(
							PanelBody,
							{ title: __('Display', 'casablanca-booking'), initialOpen: true },
							el(SelectControl, {
								label: __('Overview layout', 'casablanca-booking'),
								value: attributes.overviewLayout || 'grid',
								options: [
									{ label: __('Grid', 'casablanca-booking'), value: 'grid' },
									{ label: __('List', 'casablanca-booking'), value: 'list' },
								],
								onChange: function (value) {
									setAttributes({ overviewLayout: value });
								},
							}),
							el(SelectControl, {
								label: __('Card link', 'casablanca-booking'),
								value: attributes.cardLinkType || 'book',
								options: [
									{ label: __('Book (IBE)', 'casablanca-booking'), value: 'book' },
									{ label: __('Details page', 'casablanca-booking'), value: 'details' },
								],
								onChange: function (value) {
									setAttributes({ cardLinkType: value });
								},
							}),
							el(DetailPageControl, { attributes: attributes, setAttributes: setAttributes }),
							displayToggles(attributes, setAttributes),
							overviewDescriptionControls(attributes, setAttributes)
						),
						el(PanelBody, { title: __('Labels', 'casablanca-booking'), initialOpen: false }, labelControls(attributes, setAttributes))
					),
					el(Preview, { name: props.name, title: props.title || 'CASABLANCA Packages', attributes: attributes })
				);
			},
		},
		{
			name: 'casablanca/room-detail',
			title: __('CASABLANCA Room details', 'casablanca-booking'),
			edit: function (props) {
				var attributes = props.attributes;
				var setAttributes = props.setAttributes;
				return el(
					Fragment,
					null,
					el(
						InspectorControls,
						null,
						el(
							PanelBody,
							{ title: __('Room details', 'casablanca-booking'), initialOpen: true },
							appearanceControl(attributes, setAttributes),
							el(TextControl, {
								label: __('Fallback room type ID', 'casablanca-booking'),
								value: attributes.fallbackRoomType || '',
								help: __(
									'Used when no URL slug is present (editor preview). On the live detail page the slug wins.',
									'casablanca-booking'
								),
								onChange: function (value) {
									setAttributes({ fallbackRoomType: value });
								},
							}),
							el(RangeControl, {
								label: __('Price window (days)', 'casablanca-booking'),
								value: attributes.windowDays || 90,
								min: 7,
								max: 180,
								onChange: function (value) {
									setAttributes({ windowDays: value });
								},
							}),
							el(RangeControl, {
								label: __('Default adults', 'casablanca-booking'),
								value: attributes.defaultAdults || 2,
								min: 1,
								max: 10,
								onChange: function (value) {
									setAttributes({ defaultAdults: value });
								},
							}),
							el(RangeControl, {
								label: __('Default children', 'casablanca-booking'),
								value: attributes.defaultChildren || 0,
								min: 0,
								max: 10,
								onChange: function (value) {
									setAttributes({ defaultChildren: value });
								},
							}),
							el(RangeControl, {
								label: __('Stay nights', 'casablanca-booking'),
								value: attributes.stayNights || 7,
								min: 1,
								max: 30,
								onChange: function (value) {
									setAttributes({ stayNights: value });
								},
							}),
							languageControl(attributes, setAttributes),
							ibeTargetControl(attributes, setAttributes),
							displayToggles(attributes, setAttributes)
						),
						el(
							PanelBody,
							{ title: __('Availability calendar', 'casablanca-booking'), initialOpen: true },
							detailCalendarControls(attributes, setAttributes)
						),
						el(PanelBody, { title: __('Labels', 'casablanca-booking'), initialOpen: false }, labelControls(attributes, setAttributes))
					),
					el(Preview, {
						name: props.name,
						title: props.title || 'CASABLANCA Room details',
						attributes: attributes,
					})
				);
			},
		},
		{
			name: 'casablanca/package-detail',
			title: __('CASABLANCA Package details', 'casablanca-booking'),
			edit: function (props) {
				var attributes = props.attributes;
				var setAttributes = props.setAttributes;
				return el(
					Fragment,
					null,
					el(
						InspectorControls,
						null,
						el(
							PanelBody,
							{ title: __('Package details', 'casablanca-booking'), initialOpen: true },
							appearanceControl(attributes, setAttributes),
							el(TextControl, {
								label: __('Fallback package ID', 'casablanca-booking'),
								value: attributes.fallbackPackage || '',
								help: __(
									'Used when no URL slug is present (editor preview). On the live detail page the slug wins.',
									'casablanca-booking'
								),
								onChange: function (value) {
									setAttributes({ fallbackPackage: value });
								},
							}),
							el(RangeControl, {
								label: __('Price window (days)', 'casablanca-booking'),
								value: attributes.windowDays || 90,
								min: 7,
								max: 180,
								onChange: function (value) {
									setAttributes({ windowDays: value });
								},
							}),
							el(RangeControl, {
								label: __('Default adults', 'casablanca-booking'),
								value: attributes.defaultAdults || 2,
								min: 1,
								max: 10,
								onChange: function (value) {
									setAttributes({ defaultAdults: value });
								},
							}),
							el(RangeControl, {
								label: __('Stay nights', 'casablanca-booking'),
								value: attributes.stayNights || 7,
								min: 1,
								max: 30,
								onChange: function (value) {
									setAttributes({ stayNights: value });
								},
							}),
							languageControl(attributes, setAttributes),
							ibeTargetControl(attributes, setAttributes),
							displayToggles(attributes, setAttributes)
						),
						el(
							PanelBody,
							{ title: __('Availability calendar', 'casablanca-booking'), initialOpen: true },
							detailCalendarControls(attributes, setAttributes)
						),
						el(PanelBody, { title: __('Labels', 'casablanca-booking'), initialOpen: false }, labelControls(attributes, setAttributes))
					),
					el(Preview, {
						name: props.name,
						title: props.title || 'CASABLANCA Package details',
						attributes: attributes,
					})
				);
			},
		},
		{
			name: 'casablanca/price-teaser',
			title: __('CASABLANCA Price Teaser', 'casablanca-booking'),
			edit: function (props) {
				var attributes = props.attributes;
				var setAttributes = props.setAttributes;
				return el(
					Fragment,
					null,
					el(
						InspectorControls,
						null,
						el(
							PanelBody,
							{ title: __('Price teaser', 'casablanca-booking'), initialOpen: true },
							appearanceControl(attributes, setAttributes),
							el(TextControl, {
								label: __('Room type ID', 'casablanca-booking'),
								value: attributes.roomType || '',
								onChange: function (value) {
									setAttributes({ roomType: value });
								},
							}),
							el(RangeControl, {
								label: __('Window days', 'casablanca-booking'),
								value: attributes.windowDays,
								min: 7,
								max: 365,
								onChange: function (value) {
									setAttributes({ windowDays: value });
								},
							}),
							languageControl(attributes, setAttributes)
						)
					),
					el(Preview, { name: props.name, title: props.title || 'CASABLANCA Price Teaser', attributes: attributes })
				);
			},
		},
	];

	blockDefs.forEach(function (def) {
		registerBlockType(def.name, {
			edit: def.edit,
			save: function () {
				return null;
			},
		});
	});
})(window.wp);
