import { Injectable, PLATFORM_ID, inject } from '@angular/core';
import { isPlatformBrowser } from '@angular/common';

declare const google: any;

@Injectable({ providedIn: 'root' })
export class GoogleIdentityService {

  private readonly platformId = inject(PLATFORM_ID);
  private scriptCargado?: Promise<void>;

  /** Carga el SDK de Google Identity Services una sola vez (solo navegador). */
  cargarScript(): Promise<void> {
    if (!isPlatformBrowser(this.platformId)) {
      return Promise.reject('Google Identity solo corre en el navegador');
    }

    if (!this.scriptCargado) {
      this.scriptCargado = new Promise<void>((resolve, reject) => {
        const script = document.createElement('script');
        script.src = 'https://accounts.google.com/gsi/client';
        script.async = true;
        script.defer = true;
        script.onload = () => resolve();
        script.onerror = () => {
          this.scriptCargado = undefined; // permite reintentar
          reject('No se pudo cargar Google Identity Services');
        };
        document.head.appendChild(script);
      });
    }

    return this.scriptCargado;
  }

  /** Dibuja el botón oficial de Google y entrega el ID token en onCredential. */
  async renderizarBoton(
    contenedor: HTMLElement,
    clientId: string,
    onCredential: (credential: string) => void
  ): Promise<void> {
    await this.cargarScript();

    google.accounts.id.initialize({
      client_id: clientId,
      callback: (resp: { credential: string }) => onCredential(resp.credential),
      ux_mode: 'popup',
    });

    google.accounts.id.renderButton(contenedor, {
      theme: 'outline',
      size: 'large',
      text: 'continue_with',
      shape: 'rectangular',
      locale: 'es',
      width: 320,
    });
  }
}