import { Controller } from '@hotwired/stimulus';

/*
 * Envía el formulario al cambiar un campo (filtros).
 * Uso: data-controller="autosubmit" en el <form>, data-action="autosubmit#submit" en el campo.
 */
export default class extends Controller {
    submit() {
        this.element.requestSubmit();
    }
}
