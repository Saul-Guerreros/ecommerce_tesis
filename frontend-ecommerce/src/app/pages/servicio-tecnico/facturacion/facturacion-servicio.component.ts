import { CommonModule } from '@angular/common';
import { Component, Input, OnChanges, SimpleChanges } from '@angular/core';
import { Observable, catchError, of, tap, throwError } from 'rxjs';
import { MedioRecepcion, TipoDocumentoFacturacion, TitularFacturacion } from '../../../core/models/checkout.model';
import { DatoFiscal } from '../../../core/models/dato-fiscal.model';
import { FacturacionServicioOpciones, GuardarFacturacionServicio } from '../../../core/models/factura-servicio.model';
import { FacturaServicioService } from '../../../core/services/factura-servicio.service';
import { FormDatoFiscalComponent } from '../../checkout/form-dato-fiscal/form-dato-fiscal.component';

/** Error ya mostrado en la tarjeta: la página solo corta el envío. */
export const ERROR_FACTURACION_SERVICIO = { facturacion: true } as const;

/**
 * Tarjeta "Datos de facturación" del pago del servicio (C4). Imita el Paso 2
 * del checkout sin tocarlo: mismas opciones C.I. / RUC, mismo modal para
 * agregar un RUC (app-form-dato-fiscal) y el mismo medio de recepción.
 *
 * La página llama a guardar() justo antes de registrar el pago:
 *   this.facturacion.guardar().pipe(switchMap(() => pago$))
 * Así, cuando el verificador confirma el pago, la factura sale con estos datos.
 */
@Component({
  selector: 'app-facturacion-servicio',
  standalone: true,
  imports: [CommonModule, FormDatoFiscalComponent],
  templateUrl: './facturacion-servicio.component.html',
  styleUrls: ['../../checkout/paso-facturacion/paso-facturacion.component.css', './facturacion-servicio.component.css'],
})
export class FacturacionServicioComponent implements OnChanges {
  @Input() numero = '';
  /** true desde que el cliente intentó enviar: muestra los mensajes en rojo. */
  @Input() intento = false;

  opciones: FacturacionServicioOpciones | null = null;
  cargando = true;

  tipoDocumento: TipoDocumentoFacturacion = 'CI';
  datoFiscalSeleccionado: DatoFiscal | null = null;
  /** Sin valor por defecto, igual que el checkout: el cliente elige. */
  medioRecepcion: MedioRecepcion | null = null;
  errorBackend: string | null = null;

  modalVisible = false;

  constructor(private service: FacturaServicioService) {}

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['numero'] && this.numero) this.cargar();
  }

  private cargar(): void {
    this.cargando = true;
    this.service.opciones(this.numero).subscribe({
      next: (res) => {
        this.opciones = res.data;
        this.precargar();
        this.cargando = false;
      },
      // Sin opciones el pago sigue: la factura sale con la C.I. (fallback del backend).
      error: () => { this.opciones = null; this.cargando = false; },
    });
  }

  /** Si ya había elegido antes (un pago anterior rechazado), se respeta. */
  private precargar(): void {
    const o = this.opciones!;
    const a = o.actual;

    this.tipoDocumento = a?.tipo_documento ?? 'CI';
    this.medioRecepcion = a?.recibe ?? null;
    this.datoFiscalSeleccionado =
      o.datos_fiscales.find((d) => d.dfi_id === a?.dfi_id)
      ?? o.datos_fiscales.find((d) => d.dfi_principal)
      ?? o.datos_fiscales[0]
      ?? null;
  }

  // ---------- Vista ----------

  get titular(): TitularFacturacion | null {
    const t = this.opciones?.titular;
    return t ? { numero: t.nrodoc || null, nombre: t.nombre, apellido: t.apellido, telefono: t.telefono, correo: t.correo ?? '' } : null;
  }

  get datosFiscales(): DatoFiscal[] {
    return this.opciones?.datos_fiscales ?? [];
  }

  get editable(): boolean {
    return !!this.opciones?.editable;
  }

  get rucInvalido(): boolean {
    return this.intento && this.tipoDocumento === 'RUC' && !this.datoFiscalSeleccionado;
  }

  get medioInvalido(): boolean {
    return this.intento && !this.medioRecepcion;
  }

  get valido(): boolean {
    if (!this.opciones || !this.editable) return true;
    if (!this.medioRecepcion) return false;
    return this.tipoDocumento === 'CI' || !!this.datoFiscalSeleccionado;
  }

  // ---------- Selección ----------

  elegirTipoDocumento(tipo: TipoDocumentoFacturacion): void {
    if (!this.editable) return;
    this.tipoDocumento = tipo;
    this.errorBackend = null;
  }

  seleccionarDatoFiscal(dato: DatoFiscal): void {
    this.datoFiscalSeleccionado = dato;
    this.errorBackend = null;
  }

  elegirMedio(medio: MedioRecepcion): void {
    if (!this.editable) return;
    this.medioRecepcion = medio;
    this.errorBackend = null;
  }

  abrirNuevo(): void {
    this.modalVisible = true;
  }

  /** RUC recién agregado: entra a la lista y queda seleccionado. */
  onGuardado(dato: DatoFiscal): void {
    this.modalVisible = false;
    if (!this.opciones) return;

    let lista = [dato, ...this.opciones.datos_fiscales.filter((d) => d.dfi_id !== dato.dfi_id)];
    if (dato.dfi_principal) lista = lista.map((d) => ({ ...d, dfi_principal: d.dfi_id === dato.dfi_id }));

    this.opciones = { ...this.opciones, datos_fiscales: lista };
    this.seleccionarDatoFiscal(dato);
  }

  // ---------- Guardar (lo llama la página) ----------

  guardar(): Observable<unknown> {
    if (!this.opciones || !this.editable) return of(null);
    if (!this.valido) return throwError(() => ERROR_FACTURACION_SERVICIO);

    const datos: GuardarFacturacionServicio = { tipo_documento: this.tipoDocumento, recibe: this.medioRecepcion! };
    if (this.tipoDocumento === 'RUC') datos.dfi_id = this.datoFiscalSeleccionado!.dfi_id;

    this.errorBackend = null;

    return this.service.guardar(this.numero, datos).pipe(
      tap((res) => (this.opciones = { ...res.data, factura: this.opciones?.factura })),
      catchError((e) => {
        const errores = e?.error?.errors as Record<string, string[]> | undefined;
        if (e?.status !== 422 || !errores) return throwError(() => e);

        // Ej.: RUC guardado con dígito verificador incorrecto, o sin celular para WhatsApp.
        this.errorBackend = Object.values(errores)[0]?.[0] ?? 'Revisá los datos de facturación.';
        return throwError(() => ERROR_FACTURACION_SERVICIO);
      }),
    );
  }
}
