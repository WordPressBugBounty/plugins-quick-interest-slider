jQuery(document).ready(function($) {
(function(factory) {
	'use strict';

	if (typeof define === 'function' && define.amd) {
		// AMD. Register as an anonymous module.
		define(['jquery'], factory);
	} else if (typeof exports === 'object') {
		// CommonJS
		module.exports = factory(require('jquery'));
	} else {
		// Browser globals
		factory(jQuery);
	}
}(function($) {
	'use strict';

	// Polyfill Number.isNaN(value)
	// https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Number/isNaN
	Number.isNaN = Number.isNaN || function(value) {
		return typeof value === 'number' && value !== value;
	};

	/**
	 * Range feature detection
	 * @return {Boolean}
	 */
	function supportsRange() {
		var input = document.createElement('input');
		input.setAttribute('type', 'range');
		return input.type !== 'text';
	}

	var pluginName = 'qis',
		pluginIdentifier = 0,
		hasInputRangeSupport = supportsRange(),
		defaults = {
			polyfill: true,
			orientation: 'horizontal',
			rangeClass: 'qis',
			disabledClass: 'qis--disabled',
			activeClass: 'qis--active',
			horizontalClass: 'qis--horizontal',
			verticalClass: 'qis--vertical',
			fillClass: 'qis__fill',
			handleClass: 'qis__handle',
			startEvent: ['mousedown', 'touchstart', 'pointerdown'],
			moveEvent: ['mousemove', 'touchmove', 'pointermove'],
			endEvent: ['mouseup', 'touchend', 'pointerup']
		},
		constants = {
			orientation: {
				horizontal: {
					dimension: 'width',
					direction: 'left',
					directionStyle: 'left',
					coordinate: 'x'
				},
				vertical: {
					dimension: 'height',
					direction: 'top',
					directionStyle: 'bottom',
					coordinate: 'y'
				}
			}
		};

	/**
	 * Delays a function for the given number of milliseconds, and then calls
	 * it with the arguments supplied.
	 *
	 * @param  {Function} fn   [description]
	 * @param  {Number}   wait [description]
	 * @return {Function}
	 */
	function delay(fn, wait) {
		var args = Array.prototype.slice.call(arguments, 2);
		return setTimeout(function(){ return fn.apply(null, args); }, wait);
	}

	/**
	 * Returns a debounced function that will make sure the given
	 * function is not triggered too much.
	 *
	 * @param  {Function} fn Function to debounce.
	 * @param  {Number}   debounceDuration OPTIONAL. The amount of time in milliseconds for which we will debounce the function. (defaults to 100ms)
	 * @return {Function}
	 */
	function debounce(fn, debounceDuration) {
		debounceDuration = debounceDuration || 100;
		return function() {
			if (!fn.debouncing) {
				var args = Array.prototype.slice.apply(arguments);
				fn.lastReturnVal = fn.apply(window, args);
				fn.debouncing = true;
			}
			clearTimeout(fn.debounceTimeout);
			fn.debounceTimeout = setTimeout(function(){
				fn.debouncing = false;
			}, debounceDuration);
			return fn.lastReturnVal;
		};
	}

	/**
	 * Check if a `element` is visible in the DOM
	 *
	 * @param  {Element}  element
	 * @return {Boolean}
	 */
	function isHidden(element) {
		return (
			element && (
				element.offsetWidth === 0 ||
				element.offsetHeight === 0 ||
				// Also Consider native `<details>` elements.
				element.open === false
			)
		);
	}

	/**
	 * Get hidden parentNodes of an `element`
	 *
	 * @param  {Element} element
	 * @return {[type]}
	 */
	function getHiddenParentNodes(element) {
		var parents = [],
			node	= element.parentNode;

		while (isHidden(node)) {
			parents.push(node);
			node = node.parentNode;
		}
		return parents;
	}

	/**
	 * Returns dimensions for an element even if it is not visible in the DOM.
	 *
	 * @param  {Element} element
	 * @param  {String}  key	 (e.g. offsetWidth Ã¢â‚¬Â¦)
	 * @return {Number}
	 */
	function getDimension(element, key) {
		var hiddenParentNodes	   = getHiddenParentNodes(element),
			hiddenParentNodesLength = hiddenParentNodes.length,
			inlineStyle			 = [],
			dimension			   = element[key];

		// Used for native `<details>` elements
		function toggleOpenProperty(element) {
			if (typeof element.open !== 'undefined') {
				element.open = (element.open) ? false : true;
			}
		}

		if (hiddenParentNodesLength) {
			for (var i = 0; i < hiddenParentNodesLength; i++) {

				// Cache style attribute to restore it later.
				inlineStyle[i] = hiddenParentNodes[i].style.cssText;

				// visually hide
				if (hiddenParentNodes[i].style.setProperty) {
					hiddenParentNodes[i].style.setProperty('display', 'block', 'important');
				} else {
					hiddenParentNodes[i].style.cssText += ';display: block !important';
				}
				hiddenParentNodes[i].style.height = '0';
				hiddenParentNodes[i].style.overflow = 'hidden';
				hiddenParentNodes[i].style.visibility = 'hidden';
				toggleOpenProperty(hiddenParentNodes[i]);
			}

			// Update dimension
			dimension = element[key];

			for (var j = 0; j < hiddenParentNodesLength; j++) {

				// Restore the style attribute
				hiddenParentNodes[j].style.cssText = inlineStyle[j];
				toggleOpenProperty(hiddenParentNodes[j]);
			}
		}
		return dimension;
	}

	/**
	 * Returns the parsed float or the default if it failed.
	 *
	 * @param  {String}  str
	 * @param  {Number}  defaultValue
	 * @return {Number}
	 */
	function tryParseFloat(str, defaultValue) {
		var value = parseFloat(str);
		return Number.isNaN(value) ? defaultValue : value;
	}

	/**
	 * Capitalize the first letter of string
	 *
	 * @param  {String} str
	 * @return {String}
	 */
	function ucfirst(str) {
		return str.charAt(0).toUpperCase() + str.substr(1);
	}

	/**
	 * Plugin
	 * @param {String} element
	 * @param {Object} options
	 */
	function Plugin(element, options) {
		this.$window			= $(window);
		this.$document		  = $(document);
		this.$element		   = $(element);
		this.options			= $.extend( {}, defaults, options );
		this.polyfill		   = this.options.polyfill;
		this.orientation		= this.$element[0].getAttribute('data-orientation') || this.options.orientation;
		this.onInit			 = this.options.onInit;
		this.onSlide			= this.options.onSlide;
		this.onSlideEnd		 = this.options.onSlideEnd;
		this.DIMENSION		  = constants.orientation[this.orientation].dimension;
		this.DIRECTION		  = constants.orientation[this.orientation].direction;
		this.DIRECTION_STYLE	= constants.orientation[this.orientation].directionStyle;
		this.COORDINATE		 = constants.orientation[this.orientation].coordinate;

		// Plugin should only be used as a polyfill
		if (this.polyfill) {
			// Input range support?
			if (hasInputRangeSupport) { return false; }
		}

		this.identifier = 'js-' + pluginName + '-' +(pluginIdentifier++);
		this.startEvent = this.options.startEvent.join('.' + this.identifier + ' ') + '.' + this.identifier;
		this.moveEvent  = this.options.moveEvent.join('.' + this.identifier + ' ') + '.' + this.identifier;
		this.endEvent   = this.options.endEvent.join('.' + this.identifier + ' ') + '.' + this.identifier;
		this.toFixed	= (this.step + '').replace('.', '').length - 1;
		this.$fill	  = $('<div class="' + this.options.fillClass + '" />');
		this.$handle	= $('<div class="' + this.options.handleClass + '" />');
		this.$range	 = $('<div class="' + this.options.rangeClass + ' ' + this.options[this.orientation + 'Class'] + '" id="' + this.identifier + '" />').insertAfter(this.$element).prepend(this.$fill, this.$handle);

		// visually hide the input
		this.$element.css({
			'position': 'absolute',
			'width': '1px',
			'height': '1px',
			'overflow': 'hidden',
			'opacity': '0'
		});

		// Store context
		this.handleDown = $.proxy(this.handleDown, this);
		this.handleMove = $.proxy(this.handleMove, this);
		this.handleEnd  = $.proxy(this.handleEnd, this);

		this.init();

		// Attach Events
		var _this = this;
		this.$window.on('resize.' + this.identifier, debounce(function() {
			// Simulate resizeEnd event.
			delay(function() { _this.update(false, false); }, 300);
		}, 20));

		this.$document.on(this.startEvent, '#' + this.identifier + ':not(.' + this.options.disabledClass + ')', this.handleDown);

		// Listen to programmatic value changes
		this.$element.on('change.' + this.identifier, function(e, data) {
			if (data && data.origin === _this.identifier) {
				return;
			}

			var value = e.target.value,
				pos = _this.getPositionFromValue(value);
			_this.setPosition(pos);
		});
	}

	Plugin.prototype.init = function() {
		this.update(true, false);

		if (this.onInit && typeof this.onInit === 'function') {
			this.onInit();
		}
	};
	Plugin.prototype.lock	= function(args) {
		this.lockvalue		= args[0];
	}
	Plugin.prototype.unlock	= function() {
		this.lockvalue		= -1;
	}

	Plugin.prototype.update = function(updateAttributes, triggerSlide) {
		updateAttributes = updateAttributes || false;

		if (updateAttributes) {
			this.min	= tryParseFloat(this.$element[0].getAttribute('min'), 0);
			this.max	= tryParseFloat(this.$element[0].getAttribute('max'), 100);
			this.value  = tryParseFloat(this.$element[0].value, Math.round(this.min + (this.max-this.min)/2));
			this.step   = tryParseFloat(this.$element[0].getAttribute('step'), 1);
		}

		this.handleDimension	= getDimension(this.$handle[0], 'offset' + ucfirst(this.DIMENSION));
		this.rangeDimension	 = getDimension(this.$range[0], 'offset' + ucfirst(this.DIMENSION));
		this.maxHandlePos	   = this.rangeDimension - this.handleDimension;
		this.grabPos			= this.handleDimension / 2;
		this.position		   = this.getPositionFromValue(this.value);

		// Consider disabled state
		if (this.$element[0].disabled) {
			this.$range.addClass(this.options.disabledClass);
		} else {
			this.$range.removeClass(this.options.disabledClass);
		}

		this.setPosition(this.position, triggerSlide);
	};

	Plugin.prototype.handleDown = function(e) {
		e.preventDefault();
		this.$document.on(this.moveEvent, this.handleMove);
		this.$document.on(this.endEvent, this.handleEnd);

		// add active class because Firefox is ignoring
		// the handle:active pseudo selector because of `e.preventDefault();`
		this.$range.addClass(this.options.activeClass);

		// If we click on the handle don't set the new position
		if ((' ' + e.target.className + ' ').replace(/[\n\t]/g, ' ').indexOf(this.options.handleClass) > -1) {
			return;
		}

		var pos		 = this.getRelativePosition(e),
			rangePos	= this.$range[0].getBoundingClientRect()[this.DIRECTION],
			handlePos   = this.getPositionFromNode(this.$handle[0]) - rangePos,
			setPos	  = (this.orientation === 'vertical') ? (this.maxHandlePos - (pos - this.grabPos)) : (pos - this.grabPos);

		this.setPosition(setPos);

		if (pos >= handlePos && pos < handlePos + this.handleDimension) {
			this.grabPos = pos - handlePos;
		}
	};

	Plugin.prototype.handleMove = function(e) {
		e.preventDefault();
		var pos = this.getRelativePosition(e);
		var setPos = (this.orientation === 'vertical') ? (this.maxHandlePos - (pos - this.grabPos)) : (pos - this.grabPos);
		this.setPosition(setPos);
	};

	Plugin.prototype.handleEnd = function(e) {
		e.preventDefault();
		this.$document.off(this.moveEvent, this.handleMove);
		this.$document.off(this.endEvent, this.handleEnd);

		this.$range.removeClass(this.options.activeClass);

		// Ok we're done fire the change event
		this.$element.trigger('change', { origin: this.identifier });

		if (this.onSlideEnd && typeof this.onSlideEnd === 'function') {
			this.onSlideEnd(this.position, this.value);
		}
	};

	Plugin.prototype.cap = function(pos, min, max) {
		if (pos < min) { return min; }
		if (pos > max) { return max; }
		return pos;
	};

	Plugin.prototype.setPosition = function(pos, triggerSlide) {
		var value, newPos;

		if (triggerSlide === undefined) {
			triggerSlide = true;
		}

		// Snapping steps
		value = this.getValueFromPosition(this.cap(pos, 0, this.maxHandlePos));
		newPos = this.getPositionFromValue(value);

		// Update ui
		this.$fill[0].style[this.DIMENSION] = (newPos + this.grabPos) + 'px';
		this.$handle[0].style[this.DIRECTION_STYLE] = newPos + 'px';
		this.setValue(value);

		// Update globals
		this.position = newPos;
		this.value = value;

		if (triggerSlide && this.onSlide && typeof this.onSlide === 'function') {
			this.onSlide(newPos, value);
		}
	};

	// Returns element position relative to the parent
	Plugin.prototype.getPositionFromNode = function(node) {
		var i = 0;
		while (node !== null) {
			i += node.offsetLeft;
			node = node.offsetParent;
		}
		return i;
	};

	Plugin.prototype.getRelativePosition = function(e) {
		// Get the offset DIRECTION relative to the viewport
		var ucCoordinate = ucfirst(this.COORDINATE),
			rangePos = this.$range[0].getBoundingClientRect()[this.DIRECTION],
			pageCoordinate = 0;

		if (typeof e.originalEvent['client' + ucCoordinate] !== 'undefined') {
			pageCoordinate = e.originalEvent['client' + ucCoordinate];
		}
		else if (
		  e.originalEvent.touches &&
		  e.originalEvent.touches[0] &&
		  typeof e.originalEvent.touches[0]['client' + ucCoordinate] !== 'undefined'
		) {
			pageCoordinate = e.originalEvent.touches[0]['client' + ucCoordinate];
		}
		else if(e.currentPoint && typeof e.currentPoint[this.COORDINATE] !== 'undefined') {
			pageCoordinate = e.currentPoint[this.COORDINATE];
		}

		return pageCoordinate - rangePos;
	};

	Plugin.prototype.getPositionFromValue = function(value) {
		var percentage, pos;
		percentage = (value - this.min)/(this.max - this.min);
		pos = (!Number.isNaN(percentage)) ? percentage * this.maxHandlePos : 0;
		return pos;
	};

	Plugin.prototype.getValueFromPosition = function(pos) {
		var percentage, value;
		percentage = ((pos) / (this.maxHandlePos || 1));
		value = this.step * Math.round(percentage * (this.max - this.min) / this.step) + this.min;
		return Number((value).toFixed(this.toFixed));
	};

	Plugin.prototype.setValue = function(value) {
		if (value === this.value && this.$element[0].value !== '') {
			return;
		}

		// Set the new value and fire the `input` event
		this.$element
			.val(value)
			.trigger('input', { origin: this.identifier });
	};

	Plugin.prototype.destroy = function() {
		this.$document.off('.' + this.identifier);
		this.$window.off('.' + this.identifier);

		this.$element
			.off('.' + this.identifier)
			.removeAttr('style')
			.removeData('plugin_' + pluginName);

		// Remove the generated markup
		if (this.$range && this.$range.length) {
			this.$range[0].parentNode.removeChild(this.$range[0]);
		}
	};

	// A really lightweight plugin wrapper around the constructor,
	// preventing against multiple instantiations
	$.fn[pluginName] = function(options) {
		var args = Array.prototype.slice.call(arguments, 1);

		return this.each(function() {
			var $this = $(this),
				data  = $this.data('plugin_' + pluginName);

			// Create a new instance.
			if (!data) {
				$this.data('plugin_' + pluginName, (data = new Plugin(this, options)));
			}

			// Make it possible to access methods from public.
			// e.g `$element.qis('method');`
			if (typeof options === 'string') {
				data[options].apply(data, args);
			}
		});
	};

	return;

}));

});

function qis_force_decimal(n) {
	
	if (n.toString().split('.')[1]) {
		return n;
	}
	
	return n.toFixed(1);
}

var qis_loan_selector = 'form.qis_form';
var qis_slider_selector = 'div.range';
var qis_hidden_output = 'input.output-hidden';

function qis_adjust_downpayment(p,d) {
	var s1			= d.find('input[type=range]'),
		s2			= p.find('input[type=range]');
		s_min		= parseFloat(s1.attr('min')),
		s_max		= parseFloat(s1.attr('max')),
		s_step		= parseFloat(s1.attr('step')),
		new_value	= 0;
			
		for (var s_i = s_min; s_i <= s_max; s_i += s_step) {
			if (s_i <= s2.val()) new_value = s_i;
			else {
				break;
			}
		}

		if (s1.val() > new_value) {
			
			s1.qis('lock',new_value);
			s1.val(new_value);
			s1.qis('update');
			
			s1.change();
			
			return true;
		} else if (s1.val() < new_value) {

			s1.qis('unlock');
			
		}
		
		return false;
}
function qisDPCalculate(e) {
	var $ 			= jQuery,
		form 		= $(e).closest(qis_loan_selector),
		rates 		= qis__rates[form.attr('id')],
		sliders 	= form.find(qis_slider_selector),
		p 			= form.find(qis_slider_selector).filter('.qis-slider-principal'),
		d 			= form.find(qis_slider_selector).filter('.qis-slider-downpayment'),
		principal 	= parseFloat(p.find('input[type=range]').val()) || 0,
		downpayment = parseFloat(d.find('input[type=range]').val()) || 0,
		s1			= d.find('input[type=range]'),
		s2			= p.find('input[type=range]');
		
		
		if (qis_adjust_downpayment(p,d)) return;
				
		s2.change();
}

function qisCalculate(e) {
	
	
	/* Change relevent element's output value */
	var $ 			= jQuery,
		form 		= $(e).closest(qis_loan_selector),
		rates 		= qis__rates[form.attr('id')],
		sliders 	= form.find(qis_slider_selector),
		p 			= form.find(qis_slider_selector).filter('.qis-slider-principal'),
		t			= form.find(qis_slider_selector).filter('.qis-slider-term'),
		i 			= form.find(qis_slider_selector).filter('.qis-slider-interest'),
		d 			= form.find(qis_slider_selector).filter('.qis-slider-downpayment'),
		principal 	= parseFloat(p.find('input[type=range]').val()) || 0,
		term 		= parseFloat(t.find('input[type=range]').val()) || ((!rates.periodslider)? rates.periodinitial:0),
		interest 	= parseFloat(i.find('input[name=loan-interest]').val()) || ((!rates.interestslider)? 1:0),
		downpayment = parseFloat(d.find('input[type=range]').val()) || 0;
	
	if (rates.terminterface == 'button') {
		var data = $('.termbutton.selected').attr('rel').split(',');
		
		data = {'term':data[0],'period':data[1]};
		rates.period = data.period[0].toUpperCase() + data.period.substring(1);
		rates.periodlabel = rates.period;
		rates.singleperiodlabel = rates.periodlabel.substring(0,rates.periodlabel.length - 1);
		
		term = data.term;
		
	}
	
	/*
		Add functionality to force downpayment to be less than principal
	*/	
	var sl = d.find('input[type=range]');
	var s_min = parseFloat(sl.attr('min')), s_max = parseFloat(sl.attr('max')), s_step = parseFloat(sl.attr('step'));
		
	
	if (principal <= downpayment) {
		
		if (qis_adjust_downpayment(p,d)) {
			downpayment = d.find('input[type=range]').val();
		}
		
	} else {
		sl.qis('unlock');
	}
	
	if ($(this).hasClass("output")) return;
	
	var nsl = ((rates.nosliderlabel)? true:false);

	/* Output principal */
	
	var pP = ((parseFloat(p.find('input[type=range]').attr('step')) % 1 !== 0)? qis_force_decimal(principal):principal);
	p.find('output').html(((!nsl)? rates.cb:'')+pP.toString().qis_separator(rates.separator)+((!nsl)? rates.ca:''));
	
	
	if (rates.textinputs !== 'slider') {

		oX = p.find('.output').eq(0);
		oX.val(pP.toString());
		
		oX.attr('recent', oX.val());
		
		rates.textamount = parseFloat(oX.val());
	}
	
	/* Output downpayment */

	var dP = ((parseFloat(d.find('input[type=range]').attr('step')) % 1 !== 0)? qis_force_decimal(downpayment):downpayment);
	d.find('output').html(((!nsl)? rates.cb:'')+dP.toString().qis_separator(rates.separator)+((!nsl)? rates.ca:''));
	if (rates.textinputs !== 'slider') {

		oX = d.find('.output').eq(0);
		oX.val(dP.toString());
		
		oX.attr('recent', oX.val());
		
		rates.textdownpayment = parseFloat(oX.val());
	}
	
	/* Singular/Plural */
	var periodlabel = rates.period;
	if (rates.periodlabel) periodlabel = rates.periodlabel;
	if (rates.singleperiodlabel && term == 1) periodlabel = rates.singleperiodlabel;
	
	/* Output term */
	t.find('output').text(term+((!nsl)? ' '+periodlabel:''));
	if (rates.textinputs !== 'slider') {
		oX = t.find('.output').eq(0);
		
		oX.val(term);
		
		oX.attr('recent', oX.val());
	}
	
	/* Output Interest */
	var iP = ((parseFloat(i.find('input[type=range]').attr('step')) % 1 !== 0)? qis_force_decimal(interest):interest);
	i.find('output').text(iP+((!nsl)? '%':''));

	if (rates.textinputs !== 'slider') {
		oX = i.find('.output').eq(0);
		
		oX.val(iP);
		
		oX.attr('recent', oX.val());
	}
	
	if (principal == 0 || term == 0 || interest == 0) {
		qisHideOutputs(form);
		return;
	} 
	
	qisShowOutputs(form);

	/* Everything below this point should happen no matter WHICH slider is moved */
	
	var R = [], cR = 0, generic_R = false;

	if (rates.interestslider) {
		R[0] = {'rate':interest};
	} else if (rates.interestselector) {
		R[0] = {'rate':rates['interestrate'+$('input[name=interestselector]:checked').val()]};
	} else if (rates.interestdropdown) {
		R[0] = {'rate':$('select[name=interestdropdown]').val()};
	}else {
		/* Gather all triggers that WOULD accomodate the current term and principal */
		var relevant = [];
		
		generic_R = [];
		
		generic_R[0] = rates.triggers[0];
		if (typeof rates.triggers[1] !== 'undefined') generic_R[1] = rates.triggers[1];
		else generic_R = false;
		
		for (i in rates.triggers) {
			switch (rates.triggertype) {
				case 'periodtrigger':
					if (rates.triggers[i].trigger <= term) relevant.push(Object.create(rates.triggers[i]));
				break;
				case 'amounttrigger':
					if (parseInt(rates.triggers[i].amttrigger) <= principal) relevant.push(Object.create(rates.triggers[i]));
				break;
			}
		}
		/* Sort all of the triggers to be "highest possible trigger" last */
		relevant.sort(function(a,b) {
			var av, bv;
			switch (rates.triggertype) {
				case 'periodtrigger':
					av = parseFloat(a.trigger);
					bv = parseFloat(b.trigger);
				break;
				case 'amounttrigger':
					av = parseFloat(a.amttrigger);
					bv = parseFloat(b.amttrigger);
				break;
			}
			
			if (av > bv) return 1;
			if (av < bv) return -1;
			return 0;
		});
		
		var triggered = relevant.pop();
		R[0] = triggered;

		if (typeof R[0] == 'undefined') R[0] = {'rate':0};
	}
	
	var outputs = [], generics = [];
	switch (rates.interesttype) {
		case 'compound':
			outputs.push(qis_compound(term,principal,downpayment,rates,R[0].rate));
			if (R[1] !== undefined) outputs.push(qis_compound(term, principal, downpayment, rates, R[1].rate));
			
			if (generic_R) {
				generics.push(qis_compound(term, principal, downpayment, rates, generic_R[0].rate));
				generics.push(qis_compound(term, principal, downpayment, rates, generic_R[1].rate));
			}
			var multiplier = rates.multiplier;
			rates.multiplier = 52;
			var weekly = qis_compound(term, principal, downpayment, rates, R[0].rate);
			rates.multiplier = 12;
			var monthly = qis_compound(term, principal, downpayment, rates, R[0].rate);
			rates.multiplier = multiplier;
		break;
		case 'amortization':
			outputs.push(qis_amortization(term, principal, downpayment, rates, R[0].rate));
			if (R[1] !== undefined) outputs.push(qis_amortization(term, principal, downpayment, rates, R[1].rate));
			
			if (generic_R) {
				generics.push(qis_amortization(term, principal, downpayment, rates, generic_R[0].rate));
				generics.push(qis_amortization(term, principal, downpayment, rates, generic_R[1].rate));
			}
			var multiplier = rates.multiplier;
			rates.multiplier = 52;
			var weekly = qis_amortization(term, principal, downpayment, rates, R[0].rate);
			rates.multiplier = 12;
			var monthly = qis_amortization(term, principal, downpayment, rates, R[0].rate);
			rates.multiplier = multiplier;
		break;
		case 'amortisation':
			outputs.push(qis_amortisation(term, principal, downpayment, rates, R[0].rate));
			if (R[1] !== undefined) outputs.push(qis_amortisation(term, principal, downpayment, rates, R[1].rate));
			
			if (generic_R) {
				generics.push(qis_amortisation(term, principal, downpayment, rates, generic_R[0].rate));
				generics.push(qis_amortisation(term, principal, downpayment, rates, generic_R[1].rate));
			}
			var multiplier = rates.multiplier;
			rates.multiplier = 52;
			var weekly = qis_amortisation(term, principal, downpayment, rates, R[0].rate);
			rates.multiplier = 12;
			var monthly = qis_amortisation(term, principal, downpayment, rates, R[0].rate);
			rates.multiplier = multiplier;
		break;
		case 'fixed':
			outputs.push(qis_fixed(term, principal, downpayment, rates, R[0].rate));
			if (R[1] !== undefined) outputs.push(qis_fixed(term, principal, downpayment, rates, R[1].rate));
			
			if (generic_R) {
				generics.push(qis_fixed(term, principal, downpayment, rates, generic_R[0].rate));
				generics.push(qis_fixed(term, principal, downpayment, rates, generic_R[1].rate));
			}
			var multiplier = rates.multiplier;
			rates.multiplier = 52;
			var weekly = qis_fixed(term, principal, downpayment, rates, R[0].rate);
			rates.multiplier = 12;
			var monthly = qis_fixed(term, principal, downpayment, rates, R[0].rate);
			rates.multiplier = multiplier;
		break;
		case 'mambu':
			outputs.push(qis_mambu(term, principal, downpayment, rates, R[0].rate));
			if (R[1] !== undefined) outputs.push(qis_mambu(term, principal, downpayment, rates, R[1].rate));
			
			if (generic_R) {
				generics.push(qis_mambu(term, principal, downpayment, rates, generic_R[0].rate));
				generics.push(qis_mambu(term, principal, downpayment, rates, generic_R[1].rate));
			}
			var multiplier = rates.multiplier;
			rates.multiplier = 52;
			var weekly = qis_mambu(term, principal, downpayment, rates, R[0].rate);
			rates.multiplier = 12;
			var monthly = qis_mambu(term, principal, downpayment, rates, R[0].rate);
			rates.multiplier = multiplier;
		break;
	}
	
	if (!outputs.length) {
		/* Do simple interest */
		outputs.push(qis_simple(term, principal, downpayment, rates, R[0].rate));
		if (R[1] !== undefined) outputs.push(qis_simple(term, principal, downpayment, rates, R[1].rate));
		
		if (generic_R) {
			generics.push(qis_simple(term, principal, downpayment, rates, generic_R[0].rate));
			generics.push(qis_simple(term, principal, downpayment, rates, generic_R[1].rate));
		}
		var multiplier = rates.multiplier;
		rates.multiplier = 52;
		var weekly = qis_simple(term, principal, downpayment, rates, R[0].rate);
		rates.multiplier = 12;
		var monthly = qis_simple(term, principal, downpayment, rates, R[0].rate);
		rates.multiplier = multiplier;
	}

	/* Add admin fee if the admin fee is set to be 'after' interest! */
	if (rates.adminfeewhen == 'afterinterest') {
		qis_adminfee_after(rates,outputs,principal,term);
		qis_adminfee_after(rates,generics,principal,term);
	}
	
	var cb = rates.cb || '';
	var ca = rates.ca || '';
	
	// Calculate repayment date
	var payment_date = qis_date_add(new Date(),rates.period,term); 
	if (rates.periodformat == 'US') {
		var payment_date_string = ("0"+(payment_date.getMonth()+1)).slice(-2) + rates.dateseperator + ("0" + payment_date.getDate()).slice(-2) + rates.dateseperator + payment_date.getFullYear();
	} else if (rates.periodformat == 'monthnum') {
		var payment_date_string = ("0" + payment_date.getDate()).slice(-2) + rates.dateseperator + ("0"+(payment_date.getMonth()+1)).slice(-2) + rates.dateseperator + payment_date.getFullYear();
	} else {
		var payment_date_string =  (payment_date.getDate()) + rates.dateseperator + qis_month_name(payment_date,rates) + rates.dateseperator + payment_date.getFullYear();
	}

	/* Apply the bar if applicable */
	if (rates.usegraph) {
		
		var principal = principal - downpayment;
		
		var total = downpayment+ +principal+ +outputs[cR].processing+ +outputs[cR].interest;

		var graphd = 100*downpayment/total;
		var graphp = 100*principal/total;
		var graphi = 100*outputs[cR].interest/total;
		var graphf = 100*outputs[cR].processing/total;
		
		if (rates.adminfeewhen == 'beforeinterest') {
			var leftf	= graphp+ +graphd;
			var lefti	= leftf+ +graphf;
		} else {
			var lefti	= graphp+ +graphd;
			var leftf	= lefti+ +graphi;
		}
		
		$('.qisBarProgress1').css({'width':graphd+'%'});
		$('.qisBarProgress2').css({'left':graphd+'%','width':graphp+'%'});
		$('.qisBarProgress3').css({'left':lefti+'%','width':graphi+'%'});
		$('.qisBarProgress4').css({'left':leftf+'%','width':graphf+'%'});

	}
	
	if (rates.applynowquery) {
		var addterms = rates.querystructure;
		var calculatorname = $('#calculatorname').val();
		console.log(formname);
		if (addterms.length > 0) {
			addterms = addterms.replace('&amp;','&');
			addterms = addterms.replace('[amount]',principal);
			addterms = addterms.replace('[term]',term);
			addterms = addterms.replace('[rate]',R[cR].rate);
			if (formname) addterms = addterms.replace('[calculator]',calculatorname);
		}
		
		$('#applybutton').attr("href", rates.applynowaction+addterms);
	}
	
	/* Display the Outputs */
	form.find('.interestrate').text(R[cR].rate+'%');
	form.find('.monthlyrate').text((outputs[cR].monthlyrate*100).toFixed(2)+'%');
	form.find('.dae').text(R[cR].dae+'%');
	form.find('.current_interest').html(cb+qis_doubledigit(outputs[cR].interest,rates).qis_separator(rates.separator)+ca);
	form.find('.final_total').html(cb+qis_doubledigit(outputs[cR].total,rates).qis_rounding(rates)+ca);
	form.find('.repayment').html((cb+qis_doubledigit(outputs[cR].repayment,rates).qis_separator(rates.separator)+ca));
	
	form.find('#repayment').html((cb+qis_doubledigit(outputs[cR].repayment,rates).qis_separator(rates.separator)+ca));

	form.find('.primary_interest').html((cb+qis_doubledigit(outputs[0].interest,rates).qis_separator(rates.separator)+ca));
	form.find('.primary_total').html((cb+qis_doubledigit(outputs[0].total,rates).qis_separator(rates.separator)+ca));
	form.find('.discount').html((cb+qis_doubledigit(outputs[0].discount,rates).qis_separator(rates.separator)+ca));

	form.find('.principle').html(cb+qis_doubledigit(principal,rates).qis_separator(rates.separator)+ca);
	
	if (rates.usedownpaymentslider) {
		form.find('.downpayment').html(cb+qis_doubledigit(downpayment,rates).qis_separator(rates.separator)+ca);
	} else {
		form.find('.downpayment').html(cb+qis_doubledigit(outputs[0].down,rates).qis_separator(rates.separator)+ca);
	}

	form.find('.mitigated').html(cb+qis_doubledigit((principal - outputs[0].down),rates).qis_separator(rates.separator)+ca);
	
	form.find('.term').text(term+' '+periodlabel);
	form.find('.repaymentdate').text(payment_date_string);
	
	form.find('.weeks').text(term * 52 / qis_term (rates));
	form.find('.years').text(Math.round(R[cR].rate * qis_term (rates))+'%');
	form.find('.weekly').html(cb+qis_doubledigit(weekly.repayment,rates).qis_separator(rates.separator)+ca);
	form.find('.monthly').html(cb+qis_doubledigit(monthly.repayment,rates).qis_separator(rates.separator)+ca);
	form.find('.annual').html(cb+qis_doubledigit((monthly.repayment * 12),rates).qis_separator(rates.separator)+ca);
	
	form.find('.processing').html(cb+qis_doubledigit(outputs[cR].processing,rates).qis_separator(rates.separator)+ca);
	
	if (outputs[1] !== undefined) {
		form.find('.secondary_interest').html((cb+qis_doubledigit(outputs[1].interest,rates).qis_separator(rates.separator)+ca));
		form.find('.secondary_total').html((cb+qis_doubledigit(outputs[1].total,rates).qis_separator(rates.separator)+ca));
	}
	
	// Fixed Rates
	if (rates.percentarr && rates.percentarr.length > 0) {
		for (j = 0; j < rates.percentarr.length; j++) {
			var k = j + 1;
			form.find('.percentages'+k).html(cb+qis_doubledigit((principal * rates.percentarr[j] / 100),rates).qis_separator(rates.separator)+ca);
		}
	}
	
	// Fill in generic primary and secondary
	if (generic_R !== false) {
		form.find('.generic_primary').html((cb+qis_doubledigit(generics[0].total,rates).qis_separator(rates.separator)+ca));
		form.find('.generic_secondary').html((cb+qis_doubledigit(generics[1].total,rates).qis_separator(rates.separator)+ca));
	}
	
	/* Fill the data into the hidden fields */
	form.find('input[name=repayment]').val(qis_doubledigit(outputs[cR].repayment,rates))
	form.find('input[name=totalamount]').val(qis_doubledigit(outputs[cR].total,rates))
	form.find('input[name=rate]').val(R[cR].rate)
}

function qis_month_name(payment_date,rates) {
	
	var month = payment_date.getMonth();
	var monthname = rates.shortmonths[month];
	return monthname;
}

function qis_doubledigit(num,rates) {
	
	if (rates.decimals == 'none') return Math.round(num).toString();
	var n = num.toFixed(2);
	if (rates.decimals == 'float') return n.replace('.00','');
	return n;
	
}

function qis_adminfee(rates,P,T) {
	
	var termfee = 0, adminfee = 0;
	if (rates.adminfee && rates.adminfeewhen == 'beforeinterest') {
		adminfee = P * (rates.adminfeevalue * .01);
		if (rates.adminfeetype != 'percent') {
			adminfee = rates.adminfeevalue;
		}
	}
	if (rates.termfee && rates.adminfeewhen == 'beforeinterest') {
		termfee = T * (rates.termfeevalue);
		adminfee = adminfee + termfee;
	}

	if (adminfee && adminfee < rates.adminfeemin && rates.adminfeemin != false) adminfee = rates.adminfeemin;
	if (adminfee && adminfee > rates.adminfeemax && rates.adminfeemax != false) adminfee = rates.adminfeemax;
	
	return {'total':P+adminfee,'processing':adminfee};
}

function qis_adminfee_after(rates,outputs,P,T) {

	var adminfee = 0, termfee = 0;
	
	for (i in outputs) {

		if (rates.adminfee && rates.adminfeewhen == 'afterinterest') {
			adminfee = P * (rates.adminfeevalue * .01);
			if (rates.adminfeetype != 'percent') {
				adminfee = rates.adminfeevalue;
			}
		}
		if (rates.termfee && rates.adminfeewhen == 'afterinterest') {
			termfee = T * (rates.termfeevalue);
			adminfee = adminfee + termfee;
		}
		
		if (adminfee && adminfee < rates.adminfeemin && rates.adminfeemin != false) adminfee = rates.adminfeemin;
		if (adminfee && adminfee > rates.adminfeemax && rates.adminfeemax != false) adminfee = rates.adminfeemax;

		outputs[i].total = outputs[i].total + adminfee;
		outputs[i].repayment = outputs[i].repayment + adminfee/(T * rates.multiplier);
		outputs[i].processing = adminfee;
	}
}

function qis_down_payment(rates,principal,downpaymentslider) {

	var downpayment = 0;
	if (rates['usedownpayment']) { 
		if (rates.downpaymentfixed) downpayment += parseFloat(rates.downpaymentfixed);
		if (rates.downpaymentpercent) downpayment += parseFloat( (principal * rates.downpaymentpercent)/100);
	}
	if (rates['usedownpaymentslider']) {
		downpayment += downpaymentslider;
	}
	
	
	return {'original':principal,'mitigated':principal - downpayment,'down':downpayment};
}
function qis_fixed(term, principal, downpayment, rates, rate) {
	
	var DWN	= qis_down_payment(rates,principal,downpayment);
	var preP= qis_adminfee(rates,DWN.mitigated,term);
	var P	= preP.total;
	var T	= term;
	var V	= rates.multiplier;
	var N	= V * T;
	var A 	= rate;
	var J 	= (A * .01);
	var I	= P * J;
	var R	= P + I;
	var D	= I * (rates.discount * .01);
	/* Apply Discount */
	R = R - D;

	var M	= R / N;
	
	return {'repayment':M,'total':R,'interest':I,'discount':D,'processing':preP.processing,'down':DWN.down};
}

function qis_simple(term,principal,downpayment,rates,rate) {
	
	var DWN	= qis_down_payment(rates,principal,downpayment);
	var preP= qis_adminfee(rates,DWN.mitigated,term);
	var P	= preP.total;
	var T 	= term;
	var A   = rate;
	var V 	= rates.multiplier;
	var N	= V * T;
	var R	= P * (1 + (A*T/100));
	var I	= R - P;
	
	var D	= I * (rates.discount * .01);
	/* Apply Discount */
	R = R - D;
	
	var M	= R / N;
	
	return {'repayment':M,'total':R,'interest':I,'discount':D,'processing':preP.processing,'down':DWN.down};
}

function qis_compound(term, principal, downpayment, rates, rate) {
	
	var DWN	= qis_down_payment(rates,principal,downpayment);
	var preP= qis_adminfee(rates,DWN.mitigated,term);
	var P	= preP.total;
	var T	= term;
	var V	= rates.multiplier;
	var N	= V * T;
	var A 	= rate;
	var R	= P * Math.pow(1+A/100,T);
	var I	= R - P;
	
	var D	= I * (rates.discount * .01);
	/* Apply Discount */
	R = R - D;
	
	var M	= R / N;

	return {'repayment':M,'total':R,'interest':I,'discount':D,'processing':preP.processing,'down':DWN.down};
}

function qis_amortisation(term, principal, downpayment, rates, rate) {
	
	var DWN	= qis_down_payment(rates,principal,downpayment);
	var preP= qis_adminfee(rates,DWN.mitigated,term);
	var P	= preP.total;
	var T	= term;
	var V 	= rates.multiplier;
	var N	= V * T;
	var C   = qis_term (rates);
	var Q   = N /C;
	var A	= rate;
	var J	= Math.pow(1 + A * .01,1/V) -1;
	if (J == 0) var M = P/Q;
	else var M 	= (J * P) / (1 - Math.pow(1 + J,-Q));

	/* Figure out the interest per payment and apply discount to that! */
	var I	= ((M * Q) - P) / Q;
	
	var D	= I * (rates.discount * .01);
	/* Apply the discount */
	M	= M - D;
	var R	= M * Q;

	return {'repayment':M,'total':R,'interest':R - P,'discount':D*Q,'processing':preP.processing,'down':DWN.down,monthlyrate:J};
}

function qis_amortization(term, principal, downpayment, rates, rate) {

	var DWN	= qis_down_payment(rates,principal,downpayment);
	var preP= qis_adminfee(rates,DWN.mitigated,term);
	var P	= preP.total;
	var T	= term;
	var V 	= rates.multiplier;
	var N	= V * T;
	var C   = qis_term(rates);
	var Q   = N / C;
	var A	= rate;
	var K   = A * .01 / V;
	if (K == 0) var M = P/Q;
	else var M 	= (K * P) / (1 - Math.pow(1 + K,-Q));
	
	/* Figure out the interest per payment and apply discount to that! */
	var I	= ((M * Q) - P) / Q;
	
	var D	= I * (rates.discount * .01);
	/* Apply the discount */
	M	= M - D;
	var R	= M * Q;
	
	return {'repayment':M,'total':R,'interest':R - P,'discount':D*Q,'processing':preP.processing,'down':DWN.down,monthlyrate:K};
}

function qis_mambu(term,principal,downpayment,rates,rate) {
	
	var DWN	= qis_down_payment(rates,principal,downpayment);
	var preP= qis_adminfee(rates,DWN.mitigated,term);
	var P	= preP.total;
	var T 	= term;
	var A   = rate;
	var V 	= rates.multiplier;
	var N	= V * T;
	var R	= P * (1 + (A*T/100));
	var I	= P * A * 12 * T / 36500;
	var R   = P + I;
	
	var D	= I * (rates.discount * .01);
	/* Apply Discount */
	R = R - D;
	
	var M	= R / N;
	
	return {'repayment':M,'total':R,'interest':I,'discount':D,'processing':preP.processing,'down':DWN.down};
}

function qis_term (rates) {
	
	var P = rates.period.toLowerCase();
	if (P == 'years') var C = 1;
	if (P == 'months') var C = 12;
	if (P == 'weeks') var C = 52;
	if (P == 'days') var C = 365;
	return C;
}

function qisManual(event) {
	
	var $ = jQuery,
		form = $(e).closest(qis_loan_selector),
		rates = qis__rates[form.attr('id')],
		e = $(this),
		p = e.closest('div.range'),
		v = e.val(),
		s = p.find('input[type=range]'),
		min = parseFloat(s.attr('min')),
		max = parseFloat(s.attr('max')),
		step = parseFloat(s.attr('step'));
		
	rates.textamount = parseFloat(v);
	
	var caret = getCaretPosition(this);

	// if (String.match(/[^0-9])
	// Make sure nothing but numbers are allowed

	var preFilter = v.replace(/[^0-9.]/g, '').split('.');
	
	var filter = preFilter.shift() + (preFilter.length ? '.' + preFilter.join('') : '');

	e.val(filter);

	v1 = parseFloat(e.val());

	/* if (v1 >= step && !(v1 % step)) { 
		if ((max >= v1) && (min <= v1)) {
			s.val(v1);
			s.change();
		}
	} */
	
	/* Compare the two values and replace the caret where it belongs :) */
	setCaretPosition(e[0],e.val().length - (String(v).length - caret));

}

function qisTest() {

	var $ = jQuery,
		form = $(e).closest(qis_loan_selector),
		rates = qis__rates[form.attr('id')],
		e = $(this),
		p = e.closest('div.range'),
		v = parseFloat(e.val()),
		s = p.find('input[type=range]'),
		min = parseFloat(s.attr('min')),
		max = parseFloat(s.attr('max'));

	
	if (qisHasEmpty(form)) qisHideOutputs(form);
	else {
		if (min > v) e.val(min);
		if (max < v) e.val(max);
		qisShowOutputs(form);
		s.val(e.val());
		s.change();
	}
}

function qisHasEmpty(f) {
	
	var $ = jQuery,
		form = f,
		rates = qis__rates[form.attr('id')],
		outputs = form.find('.output'),
		p = outputs.eq(0),
		t = outputs.eq(1),
		principal = parseFloat(p.val()) || 0,
		term = parseFloat(t.val()) || 0;
	
	if (principal == 0 || term == 0) return true;
	return false;
	
}

function qisHideOutputs(f) {
	f.find('.qis-outputs').hide();
}

function qisShowOutputs(f) {
	f.find('.qis-outputs').show();
}

function getCaretPosition(elemId) {
	
	var iCaretPos = 0;

	if (document.selection) {
		elemId.focus();
		var oSel = document.selection.createRange();
		oSel.moveStart('character', -elemId.value.length);
		iCaretPos = oSel.text.length;
	}

	else if (elemId.selectionStart || elemId.selectionStart == '0') iCaretPos = elemId.selectionStart;

	return iCaretPos;
}

function setCaretPosition(elemId, caretPos) {
	
	var elem = elemId;

	if(elem != null) {
		if(elem.createTextRange) {
			var range = elem.createTextRange();
			range.move('character', caretPos);
			range.select();
		}
		else {
			if(elem.selectionStart) {
				elem.focus();
				elem.setSelectionRange(caretPos, caretPos);
			}
			else
				elem.focus();
		}
	}
}

var qis__bubble = '<output class="rangeslider__value-bubble"></output>';

function apply_all() {
	
	var $ = jQuery;
	
	/* Show/hide application form */
	$(".apply").hide("slow");
	$(".toggle-qis").click(function(event){
		var aurl = qis_application.ajax_url
		if (!qis_application.hasOwnProperty('loaded')) {
			qis_application['loaded'] = true;
			
			$.post(qis_application.ajax_url,{'action':'qis_capture_application'},function(data) {
			});
		}
		$(this).next(".apply").slideToggle("slow");
		event.preventDefault();
		return false;
	});
	
	/* Select all relevant loan slider forms */
	$(qis_loan_selector).each(function() {
		/* Initialize sliders */
		var sliders = $(this).find('[data-qis]'), x = $(this), manualinputs = $(this).find('input.output');

		sliders.not('[name=loan-downpayment]').qis({polyfill:false,
			onSlide: function(position, value) { 
				qisCalculate(this.$element);
			}
		});
		sliders.filter('[name=loan-downpayment]').qis({polyfill:false,
			onSlide: function(position, value) { 
				qisDPCalculate(this.$element);
			}
		});
		
		manualinputs.on('input', qisManual);
		manualinputs.blur(qisTest);
		
		var form	= $(this),
			rates	= qis__rates[form.attr('id')],
			buttons	= form.find('.circle-control');
		
		// Up and down buttons
		x.find('.circle-control').click(function() {
			var holder	= $(this).closest('.range'),
				range	= holder.find('input'),
				newVal	= parseFloat(range.val()),
				step	= parseFloat(range.attr('step')),
				min		= parseFloat(range.attr('min')),
				max		= parseFloat(range.attr('max'));

			if ($(this).hasClass('minus')) { // reduce the slider value
				newVal = newVal - step;
				if (newVal > min) range.val(newVal);
				else range.val(min);
			} else { // raise the slider value
				newVal = newVal + step;
				if (newVal < max) range.val(newVal);
				else range.val(max);
			}

			range.change();
		});
		
		
		buttons.filter('.circle-down').click(function() {
			
			var range = $(this).closest('.range').find('input[type=range]');
			var v = parseFloat(range.val());
			var s = parseFloat(range.attr('step')) || 1;
			var m = parseFloat(range.attr('min'));
			var n = v - s;
			if (n < m) range.val(m); 
			else range.val(n);
			
			range.change();
			
		});
		
		buttons.filter('.circle-up').click(function() {
			
			var range = $(this).closest('.range').find('input[type=range]');
			var v = parseFloat(range.val());
			var s = parseFloat(range.attr('step')) || 1;
			var m = parseFloat(range.attr('max'));
			var n = v + s;
			if (n > m) range.val(m); 
			else range.val(n);
			
			range.change();
			
		});
		
		rates.textamount = 0;

		manualinputs.change();
		
		sliders.qis({polyfill:false,
			/* Add the bubble to the handle element */
			onInit: function() {
				/* Check if the 'rangeslider__value-bubble' is set! */
				if (qis__rates[x.attr('id')].usebubble) {
					x.find('.qis__handle').append(qis__bubble);
					x.find('.qis-slidercenter').remove();
				}
			}
		});
		
		form.find('input[name=interestselector]').change(function() {
			$(sliders[0]).change();
		});
		
		form.find('select').change(function() {
			$(sliders[0]).change();
		});
		$('.hidethis').hide();
		$(sliders[0]).change();
		
		$('.qis_form').find('input[type=text], input[type=password]').keydown(function(e) {
			if (e.keyCode == 13) {
				e.preventDefault();
				e.stopPropagation();
			}
		});
		
		form.find('.termbutton').click(function() {
			$(this).closest('.term-buttons').find('.selected').removeClass('selected');
			$(this).addClass('selected');
			
			$(sliders[0]).change();
		});
		
	});
	
	/* Add functionality to the drop downs */
	$('.qis-register select').click(function() {
		if ($(this).data('focus') == 1) {
			$(this).data('focus',0);
			$(this).blur();
		}
		else $(this).data('focus',1);
	});
	$('.qis-register select').blur(function() {
		$(this).data('focus',0);
	});
	
	/* Tooltips */
	$('.qis_tooltip_toggle').click(function(e) {

		if ($(this).find('.qis_tooltip_body').is(':visible')) {
			$('.qis_tooltip_body').hide("slow");
		}
		else {
			$('.qis_tooltip_body').hide("slow");
			$(this).find('.qis_tooltip_body').show("slow");
		}
		e.preventDefault();
		e.stopPropagation();
		
	});
	
	if (typeof qis_form != 'undefined' && qis_form != 'N/A') {
		var topoffset = qis__rates[qis_form].offset
		$('html, body').animate({
			scrollTop: $('#qis_reload').offset().top - topoffset
		}, 1000);
	}

	$('body').click(function() {
		$('.qis_tooltip_body').hide("slow");
	});
	
	/* Tiny Inputs */
	$('.qis_label_tiny').find('input, textarea').focus(function() {
		$(this).closest('.qis_label_tiny').addClass('qis_input_content');
		
	});
	$('.qis_label_tiny').find('input, textarea').blur(function() {
		if (!$(this).val()) {
			$(this).closest('.qis_label_tiny').removeClass('qis_input_content');
		}
	});
	/* Apply content classes to tiny inputs for existing content */
	$('.qis_label_tiny').find('input, textarea').each(function() {
		if ($(this).val()) {
			$(this).closest('.qis_label_tiny').addClass('qis_input_content');
		}
	});
	
	/*
		Enable the dropdown
	*/
	if (!$('#calculators').data('listener')) {
		$('#calculators').on('change',function() {
			
			$('#calculator-container').empty();
			$('#calculator-failure, #calculator-loading, #calculator-container').hide();
			
			$('#calculators').prop('disabled',true);
			
			$('#calculator-loading').show();
			/*
				Get the source for the next calculator
			*/
			$.post(qis_application.ajax_url,{'action':'qis_get_calculator','attributes':{'calculator':$(this).val()}},function(data) {
				
				if (data.success == true) {
					
					$('#calculator-failure, #calculator-loading, #calculator-container').hide();
					$('#calculator-container').html(data.data).show();
					
					apply_all();
					
				} else {
					$('#calculator-failure, #calculator-loading, #calculator-container').hide();
					
					// $('#calculator-failure').show();
				}
				
				$('#calculators').prop('disabled',false);
			},'json');
		});
	}
	
	//$('#calculators').data('listener',true);
}
jQuery(document).ready(function($) {
	
	apply_all();

});

function check() {
	
	$ = jQuery;
	$("#filechecking").show();
	$(".submit").hide();  
}

function updateValueBubble(pos, value, context) {
	
	$ = jQuery;
	pos = pos || context.position;
	value = value || context.value;
	var $valueBubble = $('.rangeslider__value-bubble', context.$range);
	var tempPosition = pos + context.grabPos;
	var position = (tempPosition <= context.handleDimension) ? context.handleDimension : (tempPosition >= context.maxHandlePos) ? context.maxHandlePos : tempPosition;

	if ($valueBubble.length) {
		$valueBubble[0].style.left = Math.ceil(position) + 'px';
		$valueBubble[0].innerHTML = value;
	}
}

function qis_date_add(date, interval, units) {

	units = parseFloat(units);
	
	var ret = new Date(date); //don't change original date
	var checkRollover = function() { if(ret.getDate() != date.getDate()) ret.setDate(0);};
	switch(interval.toLowerCase()) {
		case 'years'   :  ret.setFullYear(ret.getFullYear() + units); checkRollover();  break;
		case 'quarters':  ret.setMonth(ret.getMonth() + 3*units); checkRollover();  break;
		case 'months'  :  ret.setMonth(ret.getMonth() + units); checkRollover();  break;
		case 'weeks'   :  ret.setDate(ret.getDate() + 7*units);  break;
		case 'days'	:  ret.setDate(ret.getDate() + units);  break;
		case 'hours'   :  ret.setTime(ret.getTime() + units*3600000);  break;
		case 'minutes' :  ret.setTime(ret.getTime() + units*60000);  break;
		case 'seconds' :  ret.setTime(ret.getTime() + units*1000);  break;
		default		:  ret = undefined;  break;
	}
	return ret;
}

String.prototype.qis_separator = function(sr) {
	
	if (sr == 'none') return this;
	else if (sr == 'apostrophe')  var s = "'";
	else if (sr == 'dot')  var s = ".";
	else if (sr == 'comma')  var s = ",";
	else var s = ' ';
	var str = this.split('.');
	if (str[0].length >= 4) {
		str[0] = str[0].replace(/(\d)(?=(\d{3})+$)/g, '$1'+s);
	}
	if (sr == 'dot') var decimalsdevider = ',';
	else var decimalsdevider = '.';
	return str.join(decimalsdevider);
}

String.prototype.qis_rounding = function(rates) {
	
	var rr = rates.rounding;
	var r = 1;

	if (rr == 'tenround')  var r = 10;
	if (rr == 'hundredround') var r = 100;
	if (rr == 'thousandround') var r = 1000;
	
	if (rr == 'noround') var num = this;
	else var num = Math.round(this / r) * r;
	
	var rs = rates.separator;
	
	if (rs == 'none') return num;
	if (rs == 'apostrophe')  var s = "'";
	else if (rs == 'dot')  var s = ".";
	else if (rs == 'comma')  var s = ",";
	else var s = ' ';
	var str = num.toString().split('.');
	if (str[0].length >= 4) {
		str[0] = str[0].replace(/(\d)(?=(\d{3})+$)/g, '$1'+s);
	}
	if (rs == 'dot') var decimalsdevider = ',';
	else var decimalsdevider = '.';
	return str.join(decimalsdevider);
}