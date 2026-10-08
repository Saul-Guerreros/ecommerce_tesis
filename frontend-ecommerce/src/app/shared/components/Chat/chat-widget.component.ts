import { Component, ElementRef, ViewChild, effect, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { URL_BACKEND } from '../../../config/config';
import { AccionChat, TarjetaChat } from '../../../core/models/chat.model';
import { CarritoUiService } from '../../../core/services/carrito-ui.service';
import { CarritoService } from '../../../core/services/carrito.service';
import { ChatService } from '../../../core/services/chat.service';
import { MonedaPyPipe } from '../../pipes/moneda-py.pipe';
import { TextoChatPipe } from '../../pipes/texto-chat.pipe';

/**
 * Asistente virtual flotante (MÓDULO 6). Presentación únicamente: el estado
 * vive en ChatService y el carrito se modifica con el mismo flujo que el
 * detalle de producto (CarritoService + CarritoUiService).
 */
@Component({
  selector: 'app-chat-widget',
  standalone: true,
  imports: [FormsModule, RouterLink, MonedaPyPipe, TextoChatPipe],
  templateUrl: './chat-widget.component.html',
  styleUrl: './chat-widget.component.css',
})
export class ChatWidgetComponent {
  readonly chat = inject(ChatService);
  private readonly carrito = inject(CarritoService);
  private readonly carritoUi = inject(CarritoUiService);
  private readonly toastr = inject(ToastrService);

  @ViewChild('lista') private lista?: ElementRef<HTMLElement>;
  @ViewChild('entrada') private entrada?: ElementRef<HTMLInputElement>;

  readonly abierto = signal(false);
  texto = '';

  /** Botones ya usados ("índice-pub_id"): evita agregar dos veces. */
  readonly agregados = new Set<string>();
  procesando: string | null = null;

  readonly sugerencias = [
    'Busco una notebook para estudiar',
    '¿Hacen envíos?',
    '¿Qué formas de pago aceptan?',
    'Quiero hablar con una persona',
  ];

  constructor() {
    // Cada mensaje nuevo (o el indicador "escribiendo") baja hasta el final.
    effect(() => {
      this.chat.mensajes();
      this.chat.enviando();
      setTimeout(() => this.bajarAlFinal());
    });
  }

  alternar(): void {
    this.abierto.update((valor) => !valor);

    if (this.abierto()) {
      this.chat.restaurar();
      setTimeout(() => this.entrada?.nativeElement.focus());
    }
  }

  enviar(texto: string = this.texto): void {
    this.chat.enviar(texto);
    this.texto = '';
  }

  clave(indice: number, accion: AccionChat): string {
    return `${indice}-${accion.pub_id}`;
  }

  agregarAlCarrito(accion: AccionChat, clave: string): void {
    if (this.agregados.has(clave) || this.procesando) {
      return;
    }
    this.procesando = clave;

    this.carrito
      .agregar({ car_idpro: accion.prd_id, car_cantid: accion.cantidad, car_modpag: 'contado', car_numcuo: null })
      .subscribe({
        next: () => {
          this.agregados.add(clave);
          this.procesando = null;
          this.carritoUi.confirmarAgregado();
        },
        error: (err) => {
          this.procesando = null;
          const errores = err?.error?.errors;
          const mensaje = errores
            ? errores[Object.keys(errores)[0]]?.[0]
            : err?.error?.message;
          this.toastr.error(mensaje ?? 'Intentá nuevamente.', 'No se pudo agregar');
        },
      });
  }

  /** En el celular el panel ocupa toda la pantalla: se cierra al abrir un producto. */
  alAbrirProducto(): void {
    if (window.innerWidth < 640) {
      this.abierto.set(false);
    }
  }

  imagen(producto: TarjetaChat): string | null {
    if (!producto.imagen) return null;
    if (producto.imagen.startsWith('http')) return producto.imagen;
    return `${URL_BACKEND.replace(/\/$/, '')}/${producto.imagen.replace(/^\//, '')}`;
  }

  tieneStock(producto: TarjetaChat): boolean {
    return !producto.controla_stock || producto.stock > 0;
  }

  private bajarAlFinal(): void {
    const elemento = this.lista?.nativeElement;
    if (elemento) {
      elemento.scrollTop = elemento.scrollHeight;
    }
  }
}