import { Pipe, PipeTransform } from '@angular/core';

/**
 * Formato mínimo para las respuestas del asistente: párrafos, **negrita** y
 * listas (- , * , 1.). Escapa el HTML ANTES de dar formato: el texto del
 * modelo nunca se inserta como HTML crudo.
 *   <div [innerHTML]="mensaje.texto | textoChat"></div>
 */
@Pipe({ name: 'textoChat', standalone: true })
export class TextoChatPipe implements PipeTransform {
  transform(texto: string | null | undefined): string {
    if (!texto) {
      return '';
    }

    const seguro = texto.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    let html = '';
    let enLista = false;

    for (const linea of seguro.split('\n')) {
      const item = linea.match(/^\s*(?:[-*•]|\d+\.)\s+(.*)$/);

      if (item) {
        html += (enLista ? '' : '<ul>') + `<li>${item[1]}</li>`;
        enLista = true;
        continue;
      }
      if (enLista) {
        html += '</ul>';
        enLista = false;
      }
      if (linea.trim()) {
        html += `<p>${linea}</p>`;
      }
    }

    if (enLista) {
      html += '</ul>';
    }

    return html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
  }
}