document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
        if (!confirm(form.dataset.confirm)) {
            e.preventDefault();
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
