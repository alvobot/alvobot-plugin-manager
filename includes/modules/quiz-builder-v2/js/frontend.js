/**
 * Quiz Builder V2 — Frontend runtime
 *
 * Handles question navigation, progress bar, lead capture, and redirect.
 * Zero dependencies — vanilla JS.
 */
(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  ready(function () {
    document.querySelectorAll('.qbv2').forEach(initQuiz);
  });

  function initQuiz(el) {
    var questions  = el.querySelectorAll('.qbv2__question');
    var total      = questions.length;
    if (!total) return;

    var current     = 0;
    var redirectUrl = el.dataset.redirect || '';
    var enableLead  = el.dataset.enableLead === '1';
    var nonce       = el.dataset.nonce || '';
    var ajaxUrl     = el.dataset.ajax || '';
    var quizId      = el.dataset.quizId || '';
    var answered    = []; // stores chosen URL per question ('' if none)

    var steps      = el.querySelectorAll('.qbv2__step');
    var lines      = el.querySelectorAll('.qbv2__step-line');
    var stepCurrent = el.querySelector('.qbv2__step-current');
    var leadEl     = el.querySelector('.qbv2__lead');

    updateProgress();

    // ── Answer click ──────────────────────────────────
    el.addEventListener('click', function (e) {
      var btn = e.target.closest('.qbv2__ans');
      if (!btn) return;

      var qBlock = btn.closest('.qbv2__question');
      if (!qBlock || !qBlock.classList.contains('qbv2__question--active')) return;

      // Disable all answers in this question
      qBlock.querySelectorAll('.qbv2__ans').forEach(function (b) {
        b.disabled = true;
      });
      btn.classList.add('qbv2__ans--picked');

      var answerUrl = btn.dataset.url || '';
      answered[current] = answerUrl;

      setTimeout(function () {
        // If answer has a specific URL and this is NOT the last question,
        // just advance. If last question, go to lead or redirect.
        if (current < total - 1) {
          goTo(current + 1);
        } else {
          // Last question answered
          finishQuiz(answerUrl);
        }
      }, 400);
    });

    // ── Step dot click (navigate back) ─────────────────
    steps.forEach(function (step, i) {
      step.addEventListener('click', function () {
        if (i < current && typeof answered[i] !== 'undefined') {
          goTo(i);
        }
      });
    });

    // ── Lead form ─────────────────────────────────────
    if (leadEl) {
      var form = leadEl.querySelector('.qbv2__lead-form');
      if (form) {
        form.addEventListener('submit', function (e) {
          e.preventDefault();
          var submitBtn = form.querySelector('.qbv2__lead-btn');
          if (submitBtn) submitBtn.disabled = true;

          var fd = new FormData();
          fd.append('action', 'alvobot_qbv2_lead');
          fd.append('nonce', nonce);
          fd.append('quiz_id', quizId);
          fd.append('lead_name', form.querySelector('[name="lead_name"]').value);
          fd.append('lead_email', form.querySelector('[name="lead_email"]').value);

          fetch(ajaxUrl, { method: 'POST', body: fd })
            .then(function () { doRedirect(); })
            .catch(function () { doRedirect(); }); // redirect even if save fails
        });
      }
    }

    // ── Core functions ────────────────────────────────
    function goTo(idx) {
      questions[current].classList.remove('qbv2__question--active');
      questions[current].setAttribute('aria-hidden', 'true');

      current = idx;

      questions[current].classList.add('qbv2__question--active');
      questions[current].removeAttribute('aria-hidden');

      // If going back to an already-answered question, keep buttons as-is
      updateProgress();
    }

    function updateProgress() {
      steps.forEach(function (step, i) {
        step.classList.remove('qbv2__step--active', 'qbv2__step--done');
        if (i < current) {
          step.classList.add('qbv2__step--done');
        } else if (i === current) {
          step.classList.add('qbv2__step--active');
        }
      });
      lines.forEach(function (line, i) {
        if (i < current) {
          line.classList.add('qbv2__step-line--done');
        } else {
          line.classList.remove('qbv2__step-line--done');
        }
      });
      if (stepCurrent) stepCurrent.textContent = current + 1;
    }

    function finishQuiz(lastAnswerUrl) {
      // Determine final redirect: last answer URL > quiz default
      var finalUrl = lastAnswerUrl || redirectUrl;

      if (enableLead && leadEl) {
        // Hide last question, show lead form
        questions[current].classList.remove('qbv2__question--active');
        questions[current].setAttribute('aria-hidden', 'true');

        // Update stepper to all done
        steps.forEach(function (step) {
          step.classList.remove('qbv2__step--active');
          step.classList.add('qbv2__step--done');
        });
        lines.forEach(function (line) {
          line.classList.add('qbv2__step-line--done');
        });

        // Hide step label
        var labelEl = el.querySelector('.qbv2__step-label');
        if (labelEl) labelEl.style.display = 'none';

        // Store final URL for after form submit
        el.dataset.finalUrl = finalUrl;

        leadEl.classList.add('qbv2__lead--active');
        leadEl.removeAttribute('aria-hidden');
      } else {
        // No lead capture — redirect immediately
        if (finalUrl) window.location.href = finalUrl;
      }
    }

    function doRedirect() {
      var url = el.dataset.finalUrl || redirectUrl;
      if (url) window.location.href = url;
    }
  }
})();
