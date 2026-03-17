/* Quiz Funnel — Frontend (vanilla JS, no dependencies) */
(function () {
	'use strict';

	function initQuiz(wrap) {
		var questions = wrap.querySelectorAll('.aqf__q');
		var steps     = wrap.querySelectorAll('.aqf__step');
		var finalUrl  = wrap.dataset.finalUrl || '';
		var current   = 0;
		var total     = questions.length;

		function goTo(idx) {
			// hide current
			questions[current].classList.remove('aqf__q--on');
			questions[current].setAttribute('aria-hidden', 'true');
			steps[current].classList.remove('aqf__step--on');

			current = idx;

			if (current < total) {
				questions[current].classList.add('aqf__q--on');
				questions[current].setAttribute('aria-hidden', 'false');
				steps[current].classList.add('aqf__step--on');
			}
		}

		wrap.addEventListener('click', function (e) {
			var btn = e.target.closest('.aqf__ans');
			if (!btn) return;

			// Only respond to active question
			if (parseInt(btn.dataset.qi, 10) !== current) return;

			// Lock all answers in current question
			var allBtns = questions[current].querySelectorAll('.aqf__ans');
			allBtns.forEach(function (b) { b.disabled = true; });
			btn.classList.add('aqf__ans--picked');

			var url  = btn.dataset.url;
			var next = current + 1;

			if (url) {
				setTimeout(function () { window.location.href = url; }, 350);
			} else if (next < total) {
				setTimeout(function () { goTo(next); }, 350);
			} else if (finalUrl) {
				setTimeout(function () { window.location.href = finalUrl; }, 350);
			}
		});
	}

	function boot() {
		document.querySelectorAll('.aqf').forEach(initQuiz);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
