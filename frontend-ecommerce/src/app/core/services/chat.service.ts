import { isPlatformBrowser } from '@angular/common';
import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { Injectable, PLATFORM_ID, inject, signal } from '@angular/core';
import { finalize } from 'rxjs';
import { URL_SERVICIOS } from '../../config/config';
import { HistorialChat, MensajeChat, RespuestaChat } from '../models/chat.model';

/**
 * Estado y comunicación del ChatBot (MÓDULO 6).
 *
 * El token de la conversación se guarda en localStorage: permite seguir la
 * misma conversación al recargar. Si la conversación pertenece a un cliente
 * que cerró sesión, el backend responde 404 y se empieza una nueva.
 *
 * SSR-safe: en el servidor no existe localStorage; el token vale null.
 */
@Injectable({ providedIn: 'root' })
export class ChatService {
  private readonly CLAVE_TOKEN = 'chat_token';
  private readonly URL = `${URL_SERVICIOS}/tienda/chat`;

  private readonly http = inject(HttpClient);
  private readonly esNavegador = isPlatformBrowser(inject(PLATFORM_ID));

  readonly mensajes = signal<MensajeChat[]>([]);
  readonly enviando = signal(false);

  /** El historial se pide una sola vez por carga de página. */
  private restaurado = false;

  enviar(texto: string): void {
    const limpio = texto.trim();
    if (!limpio || this.enviando()) {
      return;
    }

    this.agregar({ rol: 'usuario', texto: limpio });
    this.enviando.set(true);

    this.http
      .post<{ data: RespuestaChat }>(`${this.URL}/mensajes`, { mensaje: limpio, token: this.token })
      .pipe(finalize(() => this.enviando.set(false)))
      .subscribe({
        next: ({ data }) => {
          this.token = data.token;
          this.agregar({ rol: 'asistente', texto: data.texto, productos: data.productos, acciones: data.acciones });
        },
        error: (err: HttpErrorResponse) =>
          this.agregar({ rol: 'asistente', texto: this.mensajeError(err), error: true }),
      });
  }

  /** Redibuja la conversación guardada (solo texto: las tarjetas no se reconstruyen). */
  restaurar(): void {
    if (this.restaurado || !this.token) {
      return;
    }
    this.restaurado = true;

    this.http.get<{ data: HistorialChat }>(`${this.URL}/${this.token}`).subscribe({
      next: ({ data }) => {
        // Si el cliente ya escribió mientras cargaba, no se pisa lo nuevo.
        if (this.mensajes().length === 0) {
          this.mensajes.set(data.mensajes.map((m) => ({ rol: m.rol, texto: m.texto })));
        }
      },
      // 404: no existe, está cerrada o es de otro usuario → se empieza de cero.
      error: () => (this.token = null),
    });
  }

  nuevaConversacion(): void {
    this.token = null;
    this.mensajes.set([]);
  }

  private agregar(mensaje: MensajeChat): void {
    this.mensajes.update((lista) => [...lista, mensaje]);
  }

  private get token(): string | null {
    return this.esNavegador ? localStorage.getItem(this.CLAVE_TOKEN) : null;
  }

  private set token(valor: string | null) {
    if (!this.esNavegador) {
      return;
    }
    valor ? localStorage.setItem(this.CLAVE_TOKEN, valor) : localStorage.removeItem(this.CLAVE_TOKEN);
  }

  private mensajeError(err: HttpErrorResponse): string {
    switch (err.status) {
      case 429:
        return err.error?.message ?? 'Enviaste muchos mensajes seguidos. Esperá un momento.';
      case 422:
        return err.error?.errors?.mensaje?.[0] ?? 'Revisá tu mensaje e intentá de nuevo.';
      case 0:
        return 'No hay conexión con la tienda. Revisá tu internet e intentá de nuevo.';
      default:
        return 'Ocurrió un problema. Intentá de nuevo en unos minutos.';
    }
  }
}