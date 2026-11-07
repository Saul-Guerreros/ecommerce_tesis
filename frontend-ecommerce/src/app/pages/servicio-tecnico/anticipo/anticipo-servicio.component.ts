import { CommonModule, CurrencyPipe } from '@angular/common';
import { Component, OnInit, ViewChild } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { of, switchMap } from 'rxjs';
import { DatosBilletera, DatosTransferencia } from '../../../core/models/checkout.model';
import { PagoServicioService } from '../../../core/services/pago-servicio.service';
import { ERROR_FACTURACION_SERVICIO, FacturacionServicioComponent } from '../facturacion/facturacion-servicio.component';

type MedioAnticipo = 'TRANSFERENCIA' | 'BILLETERA_MOVIL';

/**
 * C4 - Pagar un anticipo (seña) del servicio (/mis-servicios/:numero/anticipo).
 *
 * Con el equipo recibido y antes de que esté listo, el cliente puede adelantar
 * un monto por transferencia o Tigo Money, con comprobante. Se admite UNO por
 * servicio y, si hay presupuesto aprobado, no lo supera (lo decide el backend).
 *
 * El taller valida el comprobante y emite la FACTURA DEL ANTICIPO; al terminar,
 * la factura final descuenta ese importe. Por eso los datos de facturación se
 * guardan antes: el anticipo y la final van a nombre del mismo cliente.
 *
 * Reutiliza los estilos de paso-pago y de la página de pago del saldo.
 */
@Component({
  selector: 'app-anticipo-servicio',
  standalone: true,
  imports: [CommonModule, CurrencyPipe, FormsModule, RouterModule, FacturacionServicioComponent],
  templateUrl: './anticipo-servicio.component.html',
  styleUrls: ['../../checkout/paso-pago/paso-pago.component.css', '../pago/pago-servicio.component.css'],
})
export class AnticipoServicioComponent implements OnInit {
  @ViewChild(FacturacionServicioComponent) facturacion?: FacturacionServicioComponent;

  numero = '';
  tope: number | null = null;
  datosTransferencia!: DatosTransferencia;
  datosBilletera!: DatosBilletera;

  monto: number | null = null;
  metodo: MedioAnticipo | null = null;
  comprobante: File | null = null;
  errorArchivo: string | null = null;

  cargando = true;
  enviando = false;
  intento = false;

  constructor(
    private servicio: PagoServicioService,
    private route: ActivatedRoute,
    private router: Router,
    private toastr: ToastrService,
  ) {}

  ngOnInit(): void {
    this.numero = this.route.snapshot.paramMap.get('numero') ?? '';
    this.servicio.config(this.numero).subscribe({
      next: (resp) => {
        if (!resp.anticipo?.permitido) {
          this.toastr.info('Este servicio no admite un anticipo en este momento.', 'Servicio técnico');
          this.volverAlDetalle();
          return;
        }
        this.tope = resp.anticipo.tope;
        this.datosTransferencia = resp.datos_transferencia;
        this.datosBilletera = resp.datos_billetera;
        this.cargando = false;
      },
      error: () => {
        this.toastr.error('No encontramos ese servicio en tu cuenta.', 'Servicio técnico');
        this.router.navigate(['/perfil-del-cliente'], { queryParams: { tab: 'servicios' }, replaceUrl: true });
      },
    });
  }

  volverAlDetalle(): void {
    this.router.navigate(['/mis-servicios', this.numero], { replaceUrl: true });
  }

  // ---------- Validaciones (las definitivas, en el backend) ----------

  get errorMonto(): string | null {
    const m = Number(this.monto ?? 0);
    if (!m || m < 1) return 'Ingresá el monto del anticipo.';
    if (!Number.isInteger(m)) return 'El monto se expresa en guaraníes, sin decimales.';
    if (this.tope !== null && m > this.tope) return 'No puede superar lo aprobado en el presupuesto.';
    return null;
  }

  get puedeEnviar(): boolean {
    return !this.errorMonto && !!this.metodo && !!this.comprobante;
  }

  elegirMetodo(codigo: MedioAnticipo): void {
    this.metodo = codigo;
  }

  /** Misma validación de archivo que la página de pago (extensión y 5 MB). */
  onArchivo(evento: Event): void {
    const input = evento.target as HTMLInputElement;
    const archivo = input.files?.[0] ?? null;
    const extension = archivo?.name.split('.').pop()?.toLowerCase() ?? '';

    this.errorArchivo = null;
    this.comprobante = null;

    if (archivo && !['jpg', 'jpeg', 'png', 'pdf'].includes(extension)) {
      this.errorArchivo = 'El comprobante debe ser JPG, PNG o PDF.';
    } else if (archivo && archivo.size > 5 * 1024 * 1024) {
      this.errorArchivo = 'El comprobante no puede superar los 5 MB.';
    } else {
      this.comprobante = archivo;
    }

    input.value = '';
  }

  // ---------- Envío ----------

  enviar(): void {
    if (this.enviando) return;
    this.intento = true;
    if (!this.puedeEnviar) return;

    const datos = { mpa_codigo: this.metodo!, monto: Number(this.monto), comprobante: this.comprobante! };
    const facturacion$ = this.facturacion ? this.facturacion.guardar() : of(null);
    this.enviando = true;

    // 1) datos de facturación  2) anticipo. Si el 1 falla, no se registra nada.
    facturacion$.pipe(switchMap(() => this.servicio.registrarAnticipo(this.numero, datos))).subscribe({
      next: (resp) => {
        this.enviando = false;
        this.toastr.success(resp.message, 'Servicio técnico');
        this.volverAlDetalle();
      },
      error: (e) => {
        this.enviando = false;
        if (e === ERROR_FACTURACION_SERVICIO) {
          this.toastr.warning('Revisá los datos de facturación.', 'Servicio técnico');
          return;
        }
        const errores = e.error?.errors as Record<string, string[]> | undefined;
        const primero = errores ? Object.values(errores)[0]?.[0] : null;
        this.toastr.error(primero ?? e.error?.message ?? 'No pudimos registrar tu anticipo.', 'Servicio técnico');
      },
    });
  }
}