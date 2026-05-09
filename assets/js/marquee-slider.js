/**
 * TrustScript Marquee Slider 
 * @since 1.0.0
 * @author TrustScript
 * @description Handles the marquee slider functionality, including both desktop and mobile behaviors.
 */

(function () {
	'use strict';
	
	function isElementorEditMode() {
		return (
			(window.elementorFrontend &&
				typeof window.elementorFrontend.isEditMode === 'function' &&
				window.elementorFrontend.isEditMode())
			);
		}
		
	function equalizeCardHeights(container) {
		const cards = container.querySelectorAll('.trustscript-marquee-card');
		if (cards.length === 0) return;
		cards.forEach(card => {
		card.style.height = 'auto';
	});

	let maxHeight = 0;
	cards.forEach(card => {
		const height = card.offsetHeight;
		if (height > maxHeight) {
			maxHeight = height;
		}
	});
	
	if (maxHeight > 0) {
		cards.forEach(card => {
			card.style.height = maxHeight + 'px';
		});
	}
}

	var MOBILE_BP = 724;
	
	function isMobile() {
		return window.innerWidth <= MOBILE_BP;
	}
	
	function initCarousel(container) {
		var track = container.querySelector('.trustscript-marquee-track');
		if (!track) return;
		
		var allCards = Array.from(container.querySelectorAll('.trustscript-marquee-card'));
		var totalCards = allCards.length;
		var uniqueCount = Math.ceil(totalCards / 2);
		if (uniqueCount === 0) return;
		
		var realCards = allCards.slice(0, uniqueCount);
		track.style.animation          = 'none';
		track.style.animationPlayState = 'paused';
		track.style.gap                = '0px';
		
		allCards.forEach(function (card, i) {
			card.style.display = (i < uniqueCount) ? '' : 'none';
			card.style.flex    = '0 0 auto';
		});
		
		var containerWidth = container.offsetWidth;
		
		realCards.forEach(function (card) {
			card.style.width  = containerWidth + 'px';
			card.style.height = 'auto';
		});
		
		var prependClones = [];
		var appendClones  = [];
		
		realCards.forEach(function (card) {
			var pre  = card.cloneNode(true);
			var post = card.cloneNode(true);
			pre.setAttribute('aria-hidden', 'true');
			post.setAttribute('aria-hidden', 'true');
			pre.style.width  = containerWidth + 'px';
			pre.style.flex   = '0 0 auto';
			post.style.width = containerWidth + 'px';
			post.style.flex  = '0 0 auto';
			prependClones.push(pre);
			appendClones.push(post);
		});
		
		var frag = document.createDocumentFragment();
		prependClones.forEach(function (el) { frag.appendChild(el); });
		track.insertBefore(frag, realCards[0]);
		appendClones.forEach(function (el) { track.appendChild(el); });
		
		var offset     = uniqueCount;
		var current    = 0;
		var slideIndex = offset;
		var isSnapping = false;
		var TRANSITION = 'transform 0.45s cubic-bezier(0.4, 0, 0.2, 1)';
		
		function setPosition(idx, animate) {
			track.style.transition = animate ? TRANSITION : 'none';
			track.style.transform  = 'translateX(-' + (idx * containerWidth) + 'px)';
		}
		
		setPosition(offset, false);
		var dotsEl = document.createElement('div');
		dotsEl.className = 'trustscript-marquee-dots';
		container.appendChild(dotsEl);
		
	function updateDots() {
		dotsEl.querySelectorAll('.trustscript-marquee-dot').forEach(function (d, i) {
			d.classList.toggle('trustscript-active', i === current);
		});
	}
	
	for (var di = 0; di < uniqueCount; di++) {
		(function (idx) {
			var dot = document.createElement('button');
			dot.type = 'button';
			dot.className = 'trustscript-marquee-dot' + (idx === 0 ? ' trustscript-active' : '');
			dot.setAttribute('aria-label', 'Review ' + (idx + 1) + ' of ' + uniqueCount);
			dot.addEventListener('click', function (e) {
				e.stopPropagation();
				goTo(idx);
				resetTimer();
			});
			dotsEl.appendChild(dot);
		})(di);
	}
		
	function goTo(targetReal) {
		if (isSnapping) return;
		var wrappedReal = ((targetReal % uniqueCount) + uniqueCount) % uniqueCount;
		current = wrappedReal;
		
		if (targetReal < 0) {
			slideIndex = offset - (uniqueCount - wrappedReal);
		} else if (targetReal >= uniqueCount) {
			slideIndex = offset + uniqueCount + (targetReal - uniqueCount);
		} else {
			slideIndex = offset + wrappedReal;
		}
		
		setPosition(slideIndex, true);
		updateDots();
	}
	
	track.addEventListener('transitionend', function () {
		var inPreClone  = slideIndex < offset;
		var inPostClone = slideIndex >= offset + uniqueCount;
		if (inPreClone || inPostClone) {
			isSnapping = true;
			slideIndex = offset + current;
			setPosition(slideIndex, false);
			requestAnimationFrame(function () {
				requestAnimationFrame(function () {
					isSnapping = false;
				});
			});
		}
	});
	
	var timerId = null;
	var INTERVAL = 3200;
	
	function resetTimer() {
		clearInterval(timerId);
		if (uniqueCount > 1) {
			timerId = setInterval(function () { goTo(current + 1); }, INTERVAL);
		}
	}
	
	var tsX = 0, tsY = 0, tsTime = 0;
	function onTouchStart(e) {
		tsX    = e.touches[0].clientX;
		tsY    = e.touches[0].clientY;
		tsTime = Date.now();
	}
	
	function onTouchEnd(e) {
		var dx = e.changedTouches[0].clientX - tsX;
		var dy = e.changedTouches[0].clientY - tsY;
		var dt = Date.now() - tsTime;
		
		if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 40 && dt < 600) {
			goTo(dx < 0 ? current + 1 : current - 1);
			resetTimer();
		}
	}
	
	track.addEventListener('touchstart', onTouchStart, { passive: true });
	track.addEventListener('touchend',   onTouchEnd,   { passive: true });
	
	updateDots();
	resetTimer();
	
	container._carouselState = {
		destroy: function () {
			clearInterval(timerId);
			
			prependClones.concat(appendClones).forEach(function (el) {
				if (el.parentNode) el.parentNode.removeChild(el);
			});
			
			allCards.forEach(function (card) {
				card.style.display = '';
				card.style.width   = '';
				card.style.height  = '';
				card.style.flex    = '';
			});
			
			track.style.animation          = '';
			track.style.animationPlayState = '';
			track.style.transform          = '';
			track.style.transition         = '';
			track.style.gap                = '';
			
			track.removeEventListener('touchstart', onTouchStart);
			track.removeEventListener('touchend',   onTouchEnd);
			
			if (dotsEl.parentNode) {
				dotsEl.parentNode.removeChild(dotsEl);
			}
			
			delete container._carouselState;
		}
	};
}

	function initMarqueeSlider(container, config) {
		if (container._carouselState) {
			container._carouselState.destroy();
		}
		
		if (isMobile()) {
			initCarousel(container);
			return;
		}
		
		const track = container.querySelector('.trustscript-marquee-track');
		if (!track) return;
		const speed = config.speed || 32;
		track.style.animationDuration = speed + 's';
		const direction = config.direction || 'left';
		container.setAttribute('data-direction', direction);
		const pauseOnHover = !!config.pauseOnHover;
		track.setAttribute('data-pause-on-hover', pauseOnHover ? 'true' : 'false');
		const oldEnter = container._marqueeEnter;
		const oldLeave = container._marqueeLeave;
		if (oldEnter) container.removeEventListener('mouseenter', oldEnter);
		if (oldLeave) container.removeEventListener('mouseleave', oldLeave);
		container._marqueeEnter = null;
		container._marqueeLeave = null;
		
		if (container._marqueeObserver) {
			container._marqueeObserver.disconnect();
			container._marqueeObserver = null;
		}
		
		if (container._resizeObserver) {
			container._resizeObserver.disconnect();
			container._resizeObserver = null;
		}
		
		track.style.animationPlayState = '';
		
		if (pauseOnHover) {
			
			if (isElementorEditMode()) {
				const enterHandler = function () {
					track.style.setProperty('animationPlayState', 'paused', 'important');
				};
				const leaveHandler = function () {
					track.style.setProperty('animationPlayState', 'running', 'important');
				};
				
				container.addEventListener('mouseenter', enterHandler);
				container.addEventListener('mouseleave', leaveHandler);
				container._marqueeEnter = enterHandler;
				container._marqueeLeave = leaveHandler;
			} else {
				const enterHandler = function () {
					track.style.setProperty('animationPlayState', 'paused', 'important');
				};
				const leaveHandler = function () {
					track.style.setProperty('animationPlayState', 'running', 'important');
				};
				container.addEventListener('mouseenter', enterHandler);
				container.addEventListener('mouseleave', leaveHandler);
				container._marqueeEnter = enterHandler;
				container._marqueeLeave = leaveHandler;
			}
		} else {
			track.style.animationPlayState = 'running';
		}
		
		requestAnimationFrame(function () {
			equalizeCardHeights(container);
		});
		
		if (typeof ResizeObserver !== 'undefined') {
			const cards = container.querySelectorAll('.trustscript-marquee-card');
			if (cards.length > 0) {
				let resizeTimeout;
				const resizeObserver = new ResizeObserver(function () {
					clearTimeout(resizeTimeout);
					resizeTimeout = setTimeout(function () {
						equalizeCardHeights(container);
					}, 200);
				});
				
				cards.forEach(card => {
					resizeObserver.observe(card);
				});
				
				container._resizeObserver = resizeObserver;
			}
		}
	}
	
	function initImageFirstCards() {
		document.querySelectorAll('.trustscript-image-first-card-image').forEach(function (img) {
			if (img.complete) {
				img.classList.add('trustscript-loaded');
			}
		});
	}
	
	function initAll() {
		initImageFirstCards();
		document.querySelectorAll('.trustscript-marquee-slider').forEach(function (slider) {
			const configAttr = slider.getAttribute('data-config');
			const config = configAttr ? JSON.parse(configAttr) : {};
			initMarqueeSlider(slider, config);
		});
		
		if (!window._tsMarqueeResizeAttached) {
			window._tsMarqueeResizeAttached = true;
			var resizeTimer;
			window.addEventListener('resize', function () {
				clearTimeout(resizeTimer);
				resizeTimer = setTimeout(function () {
					document.querySelectorAll('.trustscript-marquee-slider').forEach(function (slider) {
						var configAttr = slider.getAttribute('data-config');
						var config = configAttr ? JSON.parse(configAttr) : {};
						initMarqueeSlider(slider, config);
					});
				}, 250);
			});
		}
	}
	
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAll);
	} else {
		initAll();
	}
	
	window.trustscriptMarquee = {
		init: initMarqueeSlider,
		initAll: initAll
	};
	
	jQuery(window).on('elementor/frontend/init', function () {
		if (!window.elementorFrontend || !window.elementorFrontend.hooks) {
			return;
		}
		
		elementorFrontend.hooks.addAction(
			'frontend/element_ready/trustscript_reviews_showcase.default',
			function ($scope) {
				initImageFirstCards();
				var slider = $scope.find('.trustscript-marquee-slider')[0];
				if (slider) {
					var configAttr = slider.getAttribute('data-config');
					var config = configAttr ? JSON.parse(configAttr) : {};
					initMarqueeSlider(slider, config);
				}
			}
		);
	});
	
	jQuery(window).on('elementor/preview/updated', function () {
		document.querySelectorAll('.trustscript-marquee-slider').forEach(function (slider) {
			var configAttr = slider.getAttribute('data-config');
			var config = configAttr ? JSON.parse(configAttr) : {};
			setTimeout(function () {
				equalizeCardHeights(slider);
			}, 100);
		});
	});
})();