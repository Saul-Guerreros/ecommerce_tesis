import { CommonModule, CurrencyPipe } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { URL_BACKEND } from '../../config/config';
import {
  SolicitudCreditoGenerada,
  SolicitudDetalleItem,
  SolicitudLaboral,
  claseEstadoSolicitud,
} from '../../core/models/credito.model';
import { CreditoService } from '../../core/services/credito.service';

/**
 * Qué pantalla corresponde según el estado. La calcula el componente a
 * partir del estado y del flag `oferta.puede_responder` que decide el
 * backend, de modo que la regla de "se puede responder" vive en un solo
 * lugar (OfertaCreditoService).
 */
type VistaSolicitud =
  | 'oferta'        // APROBADA y vigente: el cliente decide
  | 'vencida'       // APROBADA con el plazo cumplido
  | 'aceptada'      // ACEPTADA con plazo vigente: puede continuar al checkout
  | 'plazo_vencido' // ACEPTADA con el plazo cumplido: se anula sola
  | 'convertida'    // CONVERTIDA: el pedido ya se generó
  | 'rechazada'     // RECHAZADA por el administrador: solo el motivo
  | 'anulada'       // ANULADA
  | 'en_evaluacion' // PENDIENTE / EN_REVISION
  ;

/**
 * D.2 - Detalle de la solicitud y aceptación de condiciones del crédito.
 *
 * En TODOS los estados muestra el resumen de lo que el cliente envió
 * (productos con su plan y los datos personales, laborales y
 * referencias), con el mismo diseño de la pantalla de confirmación.
 */
@Component({
  selector: 'app-solicitud-credito-detalle',
  standalone: true,
  imports: [CommonModule, CurrencyPipe, FormsModule, RouterModule],
  templateUrl: './solicitud-credito-detalle.component.html',
  styleUrl: './solicitud-credito-detalle.component.css',
})
export class SolicitudCreditoDetalleComponent implements OnInit {
  solicitud: SolicitudCreditoGenerada | null = null;
  cargando = true;
  noEncontrada = false;

  // Estado del formulario de respuesta
  aceptaCondiciones = false;
  aceptaFirma = false;
  confirmandoRechazo = false;
  enviando = false;

  private idSolicitud = 0;

  constructor(
    private route: ActivatedRoute,
    private creditoService: CreditoService,
    private toastr: ToastrService,
  ) {}

  ngOnInit(): void {
    this.idSolicitud = Number(this.route.snapshot.paramMap.get('id'));

    if (!Number.isInteger(this.idSolicitud) || this.idSolicitud <= 0) {
      this.noEncontrada = true;
      this.cargando = false;
      return;
    }

    this.cargar();
  }

  private cargar(): void {
    this.cargando = true;

    this.creditoService.detalle(this.idSolicitud).subscribe({
      next: (resp) => {
        this.solicitud = resp.data;
        this.cargando = false;
      },
      error: (err) => {
        this.cargando = false;

        if (err.status === 404) {
          this.noEncontrada = true;
          return;
        }

        this.toastr.error(err.error?.message ?? 'No pudimos cargar la solicitud.', 'Error');
      },
    });
  }

  // ---------- Vista según estado ----------
  get vista(): VistaSolicitud | null {
    if (!this.solicitud) return null;

    switch (this.solicitud.estado.codigo) {
      case 'APROBADA':
        return this.solicitud.oferta?.puede_responder ? 'oferta' : 'vencida';
      case 'ACEPTADA':
        return this.solicitud.oferta?.vencida ? 'plazo_vencido' : 'aceptada';
      case 'CONVERTIDA':
        return 'convertida';
      case 'RECHAZADA':
        return 'rechazada';
      case 'ANULADA':
        return 'anulada';
      default:
        return 'en_evaluacion';
    }
  }

  /** Condiciones y cronograma: cuando hay un crédito aprobado que mostrar. */
  get mostrarCondiciones(): boolean {
    const vista = this.vista;
    return vista === 'oferta' || vista === 'vencida' || vista === 'aceptada'
      || vista === 'plazo_vencido' || vista === 'convertida';
  }

  get puedeAceptar(): boolean {
    return this.aceptaCondiciones && this.aceptaFirma && !this.enviando;
  }

  claseEstado(codigo: string): string {
    return claseEstadoSolicitud(codigo);
  }

  textoDias(dias: number | null | undefined): string {
    if (dias === null || dias === undefined) return '';
    if (dias === 0) return 'vence hoy';
    return dias === 1 ? 'queda 1 día' : `quedan ${dias} días`;
  }

  // ---------- Presentación de los datos enviados ----------

  tituloItem(item: SolicitudDetalleItem): string {
    return item.producto?.pub_titulo ?? item.scd_descri;
  }

  resolverImagen(ruta: string | null | undefined): string {
    if (!ruta) return 'assets/images/products/placeholder.png';
    if (ruta.startsWith('http')) return ruta;
    return URL_BACKEND.replace(/\/$/, '') + '/' + ruta.replace(/^\//, '');
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

  etiquetaTipoVivienda(valor: string): string {
    return valor === 'PROPIA' ? 'Casa propia' : 'Alquiler';
  }

  /**
   * En la solicitud ya generada el backend manda la antigüedad como texto
   * ("2 años 4 meses"), mientras que en el borrador del wizard llega como
   * objeto { anios, meses, texto }. Se aceptan las dos formas.
   */
  textoAntiguedad(laboral: SolicitudLaboral): string | null {
    const antiguedad: unknown = laboral.antiguedad;

    if (!antiguedad) return null;
    if (typeof antiguedad === 'string') return antiguedad;

    return (antiguedad as { texto?: string }).texto ?? null;
  }

  // ---------- Acciones ----------

  aceptar(): void {
    if (!this.solicitud || !this.puedeAceptar) return;

    this.enviando = true;

    this.creditoService
      .aceptar(this.solicitud.scr_id, {
        acepta_condiciones: this.aceptaCondiciones,
        acepta_firma: this.aceptaFirma,
      })
      .subscribe({
        next: (resp) => {
          this.enviando = false;
          this.reiniciarFormulario();
          this.solicitud = resp.data;
          this.toastr.success(resp.message, 'Oferta aceptada');
        },
        error: (err) => this.manejarError(err, 'No pudimos registrar tu aceptación.'),
      });
  }

  rechazar(): void {
    if (!this.solicitud || this.enviando) return;

    this.enviando = true;

    this.creditoService.rechazar(this.solicitud.scr_id).subscribe({
      next: (resp) => {
        this.enviando = false;
        this.reiniciarFormulario();
        this.solicitud = resp.data;
        this.toastr.success(resp.message, 'Solicitud anulada');
      },
      error: (err) => this.manejarError(err, 'No pudimos anular la solicitud.'),
    });
  }

  private reiniciarFormulario(): void {
    this.aceptaCondiciones = false;
    this.aceptaFirma = false;
    this.confirmandoRechazo = false;
  }

  /**
   * 409: el estado cambió mientras el cliente miraba la pantalla (la oferta
   *      venció, ya la respondió desde otra pestaña, etc.). Se recarga para
   *      mostrar la situación real en vez de dejar botones que ya no valen.
   * 422: validación; se muestra el primer error junto con el aviso.
   */
  private manejarError(err: any, porDefecto: string): void {
    this.enviando = false;

    if (err.status === 409) {
      this.toastr.warning(err.error?.message ?? porDefecto, 'La solicitud cambió');
      this.reiniciarFormulario();
      this.cargar();
      return;
    }

    const errores = err.error?.errors as Record<string, string[]> | undefined;
    const primero = errores ? Object.values(errores)[0]?.[0] : undefined;

    this.toastr.error(primero ?? err.error?.message ?? porDefecto, 'Error');
  }
}