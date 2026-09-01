import { Pipe, PipeTransform } from '@angular/core';
import { Contacto } from '../models/cliente.model';

@Pipe({ name: 'contactoPrincipal', standalone: true })
export class ContactoPrincipalPipe implements PipeTransform {

  transform(contactos: Contacto[] = []): string | null {
    const pref = contactos.find(c => c.con_tipo === 'WHATSAPP')
              ?? contactos.find(c => c.con_tipo === 'CELULAR')
              ?? contactos[0];
    return pref ? pref.con_valor : null;
  }
}