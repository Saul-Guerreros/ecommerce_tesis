import { CommonModule, CurrencyPipe } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { CarritoItem } from '../../../core/models/carrito.model';
import {
  ConfigConfirmacionResponse, ResumenCredito, SolicitudLaboral, SolicitudPersonal, SolicitudReferencia,
} from '../../../core/models/credito.model';
import { CarritoService } from '../../../core/services/carrito.service';
import { CreditoService } from '../../../core/services/credito.service';
import { URL_BACKEND } from '../../../config/config';

/**
 * Paso 4 del wizard: Confirmar Solicitud. Mismo esquema visual que
 * PasoConfirmacionComponent del checkout: tarjetas con [Editar] por
 * bloque, tabla de productos con ck-conf-row, y resumen lateral sticky.
 * La diferencia funcional es el checkbox de declaración de veracidad,
 * que acá reemplaza al método de pago como condición para avanzar.
 */
@Component({
  selector: 'app-paso-confirmacion-credito',
  standalone: true,
  imports: [CommonModule, CurrencyPipe, FormsModule, RouterModule],
  templateUrl: './paso-confirmacion-credito.component.html',
  styleUrl: './paso-confirmacion-credito.component.css',
})
export class PasoConfirmacionCreditoComponent implements OnInit {
  personal!: SolicitudPersonal;
  laboral!: SolicitudLaboral;
  referencias: SolicitudReferencia[] = [];
  resumen!: ResumenCredito;
  items: CarritoItem[] = [];

  declaracion = false;

  cargando = true;
  generando = false;

  constructor(
    private creditoService: CreditoService,
    private carritoService: CarritoService,
    private toastr: ToastrService,
    private router: Router,
  ) {}

  ngOnInit(): void {
    this.cargar();
  }

  private cargar(): void {
    this.cargando = true;

    this.creditoService.configConfirmacion().subscribe({
      next: (resp: ConfigConfirmacionResponse) => {
        this.personal = resp.personal;
        this.laboral = resp.laboral;
        this.referencias = resp.referencias;
        this.resumen = resp.resumen;
        this.items = resp.items;
        this.cargando = false;
      },
      error: (err) => {
        this.cargando = false;

        // Mismo criterio que PasoConfirmacionComponent: si el borrador
        // está incompleto (el cliente llegó acá saltando pasos a mano,
        // por URL) o el carrito a crédito quedó vacío, se lo devuelve
        // al lugar donde puede resolverlo.
        this.toastr.error(
          err.error?.message ?? 'No pudimos cargar el resumen de tu solicitud.',
          'Error'
        );
        this.router.navigate(['/solicitar-credito/personal'], { replaceUrl: true });
      },
    });
  }

  // ---------- [Editar] por bloque ----------
  // Lleva al mismo Paso, pero en modo edición (flat, con Cancelar /
  // ACTUALIZAR DATOS en vez del wizard de sub-pasos). Cancelar y
  // Actualizar devuelven los dos acá, a Confirmación.

  editarPersonal(): void {
    this.router.navigate(['/solicitar-credito/personal'], { queryParams: { editar: 1 } });
  }

  editarLaboral(): void {
    this.router.navigate(['/solicitar-credito/laboral'], { queryParams: { editar: 1 } });
  }

  editarReferencias(): void {
    this.router.navigate(['/solicitar-credito/referencias'], { queryParams: { editar: 1 } });
  }

  // ---------- Items del crédito ----------

  titulo(item: CarritoItem): string {
    return item.publicacion_web?.pub_titulo ?? item.prd_descri;
  }

  resolverImagen(ruta: string | null | undefined): string {
    if (!ruta) return 'assets/images/products/placeholder.png';
    if (ruta.startsWith('http')) return ruta;
    return URL_BACKEND.replace(/\/$/, '') + '/' + ruta.replace(/^\//, '');
  }

  cuotaMensualLinea(item: CarritoItem): number {
    return Number(item.car_montocuo ?? 0) * Number(item.car_cantid);
  }

  etiquetaTipoVivienda(valor: string): string {
    return valor === 'PROPIA' ? 'Casa propia' : 'Alquiler';
  }

  etiquetaEstadoCivil(valor: string): string {
    const etiquetas: Record<string, string> = {
      SOLTERO: 'Soltero/a',
      CASADO: 'Casado/a',
      DIVORCIADO: 'Divorciado/a',
      VIUDO: 'Viudo/a',
      UNION_LIBRE: 'Unión libre',
    };
    return etiquetas[valor] ?? valor;
  }

  // ---------- Navegación y generación ----------

  volver(): void {
    this.router.navigate(['/solicitar-credito/referencias'], { replaceUrl: true });
  }

  generar(): void {
    if (!this.declaracion) {
      this.toastr.warning('Tenés que declarar que los datos proporcionados son veraces.', 'Falta confirmar');
      return;
    }

    if (this.generando) return;
    this.generando = true;

    this.creditoService.generar().subscribe({
      next: (resp) => {
        this.generando = false;
        this.creditoService.reiniciar();

        // El backend ya vació car_modpag='credito' en la misma
        // transacción; se re-sincroniza el estado local del carrito en
        // vez de reiniciar a ciegas, porque el carrito al contado (si
        // lo hay) debe seguir intacto.
        this.carritoService.cargar().subscribe();

        this.router.navigate(['/solicitud-enviada', resp.data.numero], { replaceUrl: true });
      },
      error: (err) => {
        this.generando = false;
        this.toastr.error(
          err.error?.message ?? 'No pudimos generar tu solicitud.',
          'Error'
        );
      },
    });
  }
}