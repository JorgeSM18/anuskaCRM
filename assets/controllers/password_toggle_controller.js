import { Controller } from '@hotwired/stimulus';

/*
 * Muestra/oculta el contenido de un campo de contraseña.
 * Uso: data-controller="password-toggle" en el contenedor,
 *      data-password-toggle-target="input" en el <input>,
 *      data-action="password-toggle#toggle" en el botón.
 */
export default class extends Controller {
    static targets = ['input', 'label'];

    toggle() {
        const input = this.inputTarget;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        if (this.hasLabelTarget) {
            this.labelTarget.textContent = show ? 'Ocultar' : 'Mostrar';
        }
    }
}
