(function () {
    'use strict';

    var form = document.querySelector('[data-shipping-form]');
    var description = document.getElementById('descripcion');
    var characterCount = document.querySelector('[data-character-count]');

    function updateCharacterCount() {
        if (description && characterCount) {
            characterCount.textContent = String(description.value.length);
        }
    }

    if (description) {
        description.addEventListener('input', updateCharacterCount);
        updateCharacterCount();
    }

    document.querySelectorAll('[data-phone]').forEach(function (input) {
        input.addEventListener('input', function () {
            input.value = input.value.replace(/[^0-9+(). -]/g, '');
        });
    });

    if (form) {
        form.addEventListener('submit', function () {
            var button = form.querySelector('button[type="submit"]');
            if (!button || button.disabled) {
                return;
            }

            button.disabled = true;
            button.querySelector('span').textContent = 'Generando envío…';
        });
    }
}());
