import { Component, EventEmitter, Input, Output, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import {
  AlertModule, BadgeModule, ButtonModule, FormModule, ModalModule, SpinnerModule, TableModule,
} from '@coreui/angular';
import {
  CuentaPorCobrar, CuotaCobrar, FacturaCredito, MetodoPagoOption,
} from '../models/cuenta-por-cobrar.model';
import { CuentasPorCobrarService } from '../service/cuentas-por-cobrar.service';
import { FormCobroComponent } from '../form-cobro/form-cobro.component';

@Component({
  selector: 'app-detalle-cuotas-cobrar',
  standalone: true,
  imports: [
    CommonModule, FormsModule, AlertModule, BadgeModule, ButtonModule,
    FormModule, ModalModule, SpinnerModule, TableModule, FormCobroComponent,
  ],
  templateUrl: './detalle-cuotas-cobrar.component.html',
})
export class DetalleCuotasCobrarComponent implements OnChanges {
  @Input() visible = false;
  @Input() factura: CuentaPorCobrar | null = null;
  @Input() metodos: MetodoPagoOption[] = [];

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() cobroRegistrado = new EventEmitter<void>();

  cabecera: FacturaCredito | null = null;
  cuotas: CuotaCobrar[] = [];
  saldoFactura = 0;
  deudaCliente = 0;

  cargando = false;
  anulandoId: number | null = null;

  /** cco_id de las cuotas tildadas. */
  seleccion = new Set<number>();

  cobroVisible = false;
  cuotasACobrar: CuotaCobrar[] = [];

  constructor(
    private service: CuentasPorCobrarService,
    private toastr: ToastrService,
  ) {}

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible && this.factura?.ven_id) {
      this.cargar();
    }
  }

  private cargar(): void {
    this.cargando = true;
    this.seleccion.clear();

    this.service.getDetalle(this.factura!.ven_id).subscribe({
      next: (res) => {
        this.cabecera     = res.factura;
        this.cuotas       = res.cuotas;
        this.saldoFactura = res.resumen.saldo_factura;
        this.deudaCliente = res.resumen.deuda_cliente;
        this.cargando     = false;
      },
      error: () => {
        this.toastr.error('No se pudieron cargar las cuotas', 'Error');
        this.cargando = false;
      },
    });
  }

  get tieneOtrasDeudas(): boolean {
    return this.deudaCliente > this.saldoFactura;
  }

  // ---------- Selección ----------

  /**
   * Las cuotas se cobran en orden: sólo son elegibles las N más antiguas
   * pendientes. La misma regla la valida el backend; acá se adelanta para
   * que el admin no pueda tildar algo que va a ser rechazado.
   */
  get pendientes(): CuotaCobrar[] {
    return this.cuotas.filter(c => c.puede_cobrar);
  }

  /** Elegible: es la próxima pendiente o viene justo después de una tildada. */
  elegible(c: CuotaCobrar): boolean {
    const i = this.pendientes.findIndex(p => p.cco_id === c.cco_id);
    if (i < 0) return false;

    return i === 0 || this.seleccion.has(this.pendientes[i - 1].cco_id);
  }

  seleccionada(c: CuotaCobrar): boolean {
    return this.seleccion.has(c.cco_id);
  }

  /**
   * Tildar agrega la cuota; destildar quita esa y todas las posteriores,
   * porque dejar un hueco rompería el orden consecutivo.
   */
  alternar(c: CuotaCobrar): void {
    const i = this.pendientes.findIndex(p => p.cco_id === c.cco_id);
    if (i < 0) return;

    if (this.seleccion.has(c.cco_id)) {
      this.pendientes.slice(i).forEach(p => this.seleccion.delete(p.cco_id));
      return;
    }

    this.pendientes.slice(0, i + 1).forEach(p => this.seleccion.add(p.cco_id));
  }

  get totalSeleccionado(): number {
    return this.cuotas
      .filter(c => this.seleccion.has(c.cco_id))
      .reduce((suma, c) => suma + c.cco_monto, 0);
  }

  // ---------- Estado visual ----------

  colorEstado(estado: string, vencida: boolean): string {
    if (estado === 'COBRADA') return 'success';
    if (estado === 'ANULADA') return 'secondary';
    return vencida ? 'danger' : 'warning';
  }

  etiquetaEstado(c: CuotaCobrar): string {
    if (c.cco_estado === 'PENDIENTE' && c.vencida) return 'Vencida';
    if (c.cco_estado === 'PENDIENTE') return 'Pendiente';
    return c.cco_estado === 'COBRADA' ? 'Cobrada' : 'Anulada';
  }

  // ---------- Acciones ----------

  abrirCobro(): void {
    if (this.seleccion.size === 0) return;

    this.cuotasACobrar = this.cuotas.filter(c => this.seleccion.has(c.cco_id));
    this.cobroVisible  = true;

    // Oculta este modal mientras se cobra: dos c-modal apilados no.
    this.visible = false;
    this.visibleChange.emit(false);
  }

  onCobroCerrado(): void {
    this.visible = true;
    this.visibleChange.emit(true);
  }

  onCobroGuardado(): void {
    this.cargar();
    this.cobroRegistrado.emit();
    this.onCobroCerrado();
  }

  /** Anula el recibo ENTERO: todas sus cuotas vuelven a Pendiente. */
  anular(c: CuotaCobrar): void {
    if (!c.recibo) return;

    const cubiertas = this.cuotas
      .filter(x => x.recibo?.rec_id === c.recibo!.rec_id)
      .map(x => x.cco_nrocuo);

    const aviso = cubiertas.length > 1
      ? `El recibo ${c.recibo.rec_numero} cubre las cuotas ${cubiertas.join(', ')}. Se anulan todas.\n\n`
      : '';

    const motivo = window.prompt(`${aviso}Motivo de anulación:`);
    if (motivo === null) return;

    if (motivo.trim().length < 5) {
      this.toastr.error('Indique un motivo de al menos 5 caracteres', 'Falta motivo');
      return;
    }

    this.anulandoId = c.recibo.rec_id;

    this.service.anularRecibo(c.recibo.rec_id, motivo.trim()).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Recibo anulado', 'Éxito');
        this.anulandoId = null;
        this.cargar();
        this.cobroRegistrado.emit();
      },
      error: (err) => {
        this.anulandoId = null;
        this.toastr.error(
          err?.error?.errors?.recibo?.[0] ?? err?.error?.message ?? 'No se pudo anular el recibo',
          'Error',
        );
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}