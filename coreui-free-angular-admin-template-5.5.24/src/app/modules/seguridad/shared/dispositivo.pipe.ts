import { Pipe, PipeTransform } from '@angular/core';

/** Resume un User-Agent a "Navegador · Sistema" (ej.: "Chrome · Windows"). */
@Pipe({ name: 'dispositivo', standalone: true })
export class DispositivoPipe implements PipeTransform {
  transform(userAgent: string | null | undefined): string {
    if (!userAgent) { return '—'; }

    const navegador =
      /Edg\//.test(userAgent) ? 'Edge' :
      /OPR\//.test(userAgent) ? 'Opera' :
      /Firefox\//.test(userAgent) ? 'Firefox' :
      /Chrome\//.test(userAgent) ? 'Chrome' :
      /Safari\//.test(userAgent) ? 'Safari' : null;

    const sistema =
      /Windows/.test(userAgent) ? 'Windows' :
      /Android/.test(userAgent) ? 'Android' :
      /iPhone|iPad|iPod/.test(userAgent) ? 'iOS' :
      /Mac OS X|Macintosh/.test(userAgent) ? 'macOS' :
      /Linux/.test(userAgent) ? 'Linux' : null;

    if (!navegador && !sistema) {
      // Clientes no navegador (Postman, curl, apps): se muestra el inicio del texto original
      return userAgent.length > 30 ? userAgent.slice(0, 30) + '…' : userAgent;
    }
    return [navegador, sistema].filter(Boolean).join(' · ');
  }
}
