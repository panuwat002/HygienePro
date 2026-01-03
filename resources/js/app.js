import './bootstrap';
import '../sass/app.scss';
import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

// Patch: Silently catch "backdrop" error in Bootstrap Modal
// This prevents the UI from freezing if the modal is closed too quickly or has duplicate instances
const originalDispose = bootstrap.Modal.prototype.dispose;
bootstrap.Modal.prototype.dispose = function () {
    try {
        originalDispose.call(this);
    } catch (e) {
        if (e.message && e.message.includes('backdrop')) {
            console.warn('Suppressed Bootstrap backdrop error');
        } else {
            throw e;
        }
    }
};

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();
