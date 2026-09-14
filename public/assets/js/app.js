document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
        if (!confirm(form.dataset.confirm)) {
            e.preventDefault();
        }
    });
});

(function () {
    var toggle = document.getElementById('navToggle');
    var menu = document.getElementById('navMenu');
    if (!toggle || !menu) {
        return;
    }
    toggle.addEventListener('click', function () {
        var isOpen = menu.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
    menu.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            menu.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
        });
    });
})();

function switchBidTab(form, tab) {
    form.querySelectorAll('[data-bid-tab]').forEach(function (btn) {
        var active = btn.dataset.bidTab === tab;
        btn.classList.toggle('is-active', active);
        btn.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    form.querySelectorAll('[data-bid-panel]').forEach(function (panel) {
        panel.hidden = panel.dataset.bidPanel !== tab;
    });
}

document.querySelectorAll('[data-bid-tab]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var form = btn.closest('form');
        if (form) {
            switchBidTab(form, btn.dataset.bidTab);
        }
    });
});

document.querySelectorAll('[data-strategy]').forEach(function (card) {
    card.addEventListener('click', function () {
        var form = card.closest('form');
        if (!form) {
            return;
        }
        var seconds = JSON.parse(card.dataset.strategy);
        var rows = form.querySelectorAll('.bid-step-row');
        rows.forEach(function (row, i) {
            var idInput = row.querySelector('input[name="step_id[]"]');
            var secondsInput = row.querySelector('input[name="step_seconds[]"]');
            var maxBidInput = row.querySelector('input[name="step_max_bid[]"]');
            if (idInput) {
                idInput.value = '';
            }
            if (i < seconds.length) {
                if (secondsInput) {
                    secondsInput.value = seconds[i];
                }
                if (maxBidInput) {
                    maxBidInput.value = '';
                }
                row.hidden = false;
            } else {
                if (secondsInput) {
                    secondsInput.value = '';
                }
                if (maxBidInput) {
                    maxBidInput.value = '';
                }
                row.hidden = true;
            }
        });

        var addBtn = form.querySelector('[data-add-step]');
        if (addBtn) {
            addBtn.hidden = seconds.length >= 5;
        }

        showBidStepError(form, null);
        switchBidTab(form, 'custom');

        var firstMaxBid = form.querySelector('.bid-step-row:not([hidden]) input[name="step_max_bid[]"]');
        if (firstMaxBid) {
            firstMaxBid.focus();
        }
    });
});

document.querySelectorAll('[data-add-step]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var form = btn.closest('form');
        var nextHidden = form.querySelector('.bid-step-row[hidden]');
        if (nextHidden) {
            nextHidden.hidden = false;
        }
        if (!form.querySelector('.bid-step-row[hidden]')) {
            btn.hidden = true;
        }
    });
});

function showBidStepError(form, message) {
    var existing = form.parentNode.querySelector('.bid-step-client-error');
    if (existing) {
        existing.remove();
    }
    if (!message) {
        return;
    }
    var div = document.createElement('div');
    div.className = 'flash flash-error bid-step-client-error';
    div.textContent = message;
    form.parentNode.insertBefore(div, form);
    div.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

document.querySelectorAll('form').forEach(function (form) {
    var stepRows = form.querySelectorAll('.bid-step-row');
    if (!stepRows.length) {
        return;
    }

    form.addEventListener('submit', function (e) {
        var steps = [];
        var error = null;

        stepRows.forEach(function (row) {
            var secondsInput = row.querySelector('input[name="step_seconds[]"]');
            var maxBidInput = row.querySelector('input[name="step_max_bid[]"]');
            var secondsRaw = secondsInput ? secondsInput.value.trim() : '';
            var maxBidRaw = maxBidInput ? maxBidInput.value.trim() : '';
            if (secondsRaw === '' && maxBidRaw === '') {
                return;
            }

            var seconds = parseInt(secondsRaw, 10);
            var maxBid = parseFloat(maxBidRaw);

            if (!error && (isNaN(seconds) || seconds < 1 || seconds > 60)) {
                error = 'Seconds before end must be between 1 and 60.';
            }
            if (!error && (isNaN(maxBid) || maxBid <= 0)) {
                error = 'Each max bid must be greater than 0.';
            }

            steps.push({ seconds: seconds, maxBid: maxBid });
        });

        if (!error) {
            var seen = {};
            for (var i = 0; i < steps.length; i++) {
                if (seen[steps[i].seconds]) {
                    error = 'Each bid must use a different number of seconds before the end.';
                    break;
                }
                seen[steps[i].seconds] = true;
            }
        }

        if (!error) {
            var sorted = steps.slice().sort(function (a, b) { return b.seconds - a.seconds; });
            for (var j = 1; j < sorted.length; j++) {
                if (sorted[j].maxBid < sorted[j - 1].maxBid) {
                    error = 'The bid ' + sorted[j].seconds + 's before the end can\'t be smaller than the bid ' +
                        sorted[j - 1].seconds + 's before the end — bids closer to the end must be equal or higher.';
                    break;
                }
            }
        }

        showBidStepError(form, error);
        if (error) {
            e.preventDefault();
        }
    });
});
