import { Controller } from '@hotwired/stimulus';

/*
 * Pide confirmación antes de enviar un formulario (acciones destructivas).
 * Uso: data-controller="confirm" data-confirm-message-value="¿Seguro?"
 */
export default class extends Controller {
    static values = { message: String };

    connect() {
        this.element.addEventListener('submit', this.onSubmit);
    }

    disconnect() {
        this.element.removeEventListener('submit', this.onSubmit);
    }

    onSubmit = (event) => {
        if (!window.confirm(this.messageValue || '¿Confirmar esta acción?')) {
            event.preventDefault();
        }
    };
}
