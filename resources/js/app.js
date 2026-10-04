import Alpine from 'alpinejs';
import * as bootstrap from 'bootstrap';

window.Alpine = Alpine;

// Keep the events used by Breeze's account deletion form.
document.querySelectorAll('[data-blh-modal]').forEach((element) => {
    const modal = bootstrap.Modal.getOrCreateInstance(element);
    window.addEventListener('open-modal', (event) => {
        if (event.detail === element.dataset.blhModal) modal.show();
    });
    window.addEventListener('close-modal', (event) => {
        if (event.detail === element.dataset.blhModal) modal.hide();
    });
    if (element.dataset.show === 'true') modal.show();
});

Alpine.start();
