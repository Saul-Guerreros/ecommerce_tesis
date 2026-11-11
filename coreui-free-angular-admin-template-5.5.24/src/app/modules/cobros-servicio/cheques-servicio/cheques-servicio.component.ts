import { Component, EventEmitter, Input, OnInit, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, FormsModule, ReactiveFormsModule, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { AlertModule, BadgeModule, ButtonModule, CardModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { ChequeServicio, CobroDetalle, MotivoRechazo, PagoServicio } from '../models/cobro-servicio.model';
import { CobrosServicioService } from '../service/cobros-servicio.service';
import { RecibirChequeServicioComponent } from '../recibir-cheque-servicio/recibir-cheque-servicio.component';
import { RechazarChequeServicioComponent } from '../rechazar-cheque-servicio/rechazar-cheque-servicio.component';

/**
 * Tarjeta «RECEPCIÓN DE CHEQUE» del servicio. Espejo del bloque 3.3 de
 * detalle-pedido: datos declarados, edición en línea, [Descartar],
 * [Confirmar recepción] y [Rechazar]. Los botones los habilita el backend.
 */
@Component({
  selector: 'app-cheques-servicio',
  standalone: true,
  imports: [
    CommonModule, FormsModule, ReactiveFormsModule, AlertModule, BadgeModule, ButtonModule, CardModule,
    FormModule, ModalModule, SpinnerModule, RecibirChequeServicioComponent, RechazarChequeServicioComponent,
  ],
  templateUrl: './cheques-servicio.component.html',
})
export class ChequesServicioComponent implements OnInit {
  @Input() detalle!: CobroDetalle;
  @Output() guardado = new EventEmitter<CobroDetalle>();

  motivosCheque: MotivoRechazo[] = [];
  entregaAlRecibir = true;

  formCheque: FormGroup;
  chequeEditandoId: number | null = null;
  guardandoCheque = false;

  pagoSeleccionado: PagoServicio | null = null;
  recibirVisible = false;
  rechazarVisible = false;

  descartarVisible = false;
  motivoDescarte = '';
  descartando = false;

  constructor(private fb: FormBuilder, private service: CobrosServicioService, private toastr: ToastrService) {
    this.formCheque = this.fb.group({
      tipo: ['VISTA', [Validators.required]],
      banco: ['', [Validators.required, Validators.maxLength(80)]],
      nombre: ['', [Validators.required, Validators.maxLength(150)]],
      nrodoc: ['', [Validators.required, Validators.maxLength(20)]],
      nro: ['', [Validators.required, Validators.maxLength(20)]],
      fecha_cobro: [''],
    });
  }

  ngOnInit(): void {
    this.service.config().subscribe({
      next: (res) => {
        this.motivosCheque = res.data.motivos_rechazo_cheque ?? [];
        this.entregaAlRecibir = res.data.cheque?.entrega_al_recibir ?? true;
      },
    });
  }

  // ---------- Vista ----------

  get pagosConCheque(): PagoServicio[] {
    return this.detalle.pagos.filter((p) => !!p.cheque);
  }

  get recibidos(): number {
    return this.pagosConCheque.filter((p) => ['RECIBIDO', 'ACREDITADO'].includes(p.cheque!.estado)).length;
  }

  /** Solo cuentan los vigentes: un rechazado ya no se espera. */
  get vigentes(): number {
    return this.pagosConCheque.filter((p) => !['RECHAZADO', 'NO_ENTREGADO'].includes(p.cheque!.estado)).length;
  }

  get contador(): string {
    return this.vigentes === 1
      ? (this.recibidos === 1 ? 'Cheque recibido' : 'Cheque en espera')
      : `${this.recibidos} de ${this.vigentes} cheques recibidos`;
  }

  colorEstadoCheque(estado: string | null | undefined): string {
    switch (estado) {
      case 'ACREDITADO':   return 'success';
      case 'RECIBIDO':     return 'info';
      case 'EN_ESPERA':    return 'warning';
      case 'DESCARTADO':   return 'info';
      case 'NO_ENTREGADO': return 'danger';
      case 'RECHAZADO':    return 'danger';
      default:             return 'secondary';
    }
  }

  /** «Ya venció» lo decide chq.vencido en el backend; esto solo lo traduce. */
  plazoRestante(chq: ChequeServicio): string {
    if (!chq.vence) return '';
    const ms = new Date(chq.vence).getTime() - Date.now();
    if (ms <= 0) return 'Plazo vencido';
    const horas = Math.floor(ms / 3600000);
    const minutos = Math.floor((ms % 3600000) / 60000);
    return horas > 0 ? `Quedan ${horas} h ${minutos} min` : `Quedan ${minutos} min`;
  }

  // ---------- Edición en línea ----------

  editando(p: PagoServicio): boolean {
    return this.chequeEditandoId === p.spa_id;
  }

  iniciarEdicion(p: PagoServicio): void {
    const chq = p.cheque;
    if (!chq) return;
    this.formCheque.reset({
      tipo: chq.tipo ?? 'VISTA',
      banco: chq.banco ?? '',
      nombre: chq.nombre ?? '',
      nrodoc: chq.nrodoc ?? '',
      nro: chq.nro ?? '',
      fecha_cobro: chq.fecha_cobro_iso ?? '',
    });
    this.chequeEditandoId = p.spa_id;
  }

  cancelarEdicion(): void {
    this.chequeEditandoId = null;
    this.formCheque.reset({ tipo: 'VISTA', banco: '', nombre: '', nrodoc: '', nro: '', fecha_cobro: '' });
  }

  get chequeEsDiferido(): boolean {
    return this.formCheque.value.tipo === 'DIFERIDO';
  }

  get puedeGuardarCheque(): boolean {
    if (!this.formCheque.valid || this.guardandoCheque) return false;
    return !this.chequeEsDiferido || !!this.formCheque.value.fecha_cobro;
  }

  errorCheque(campo: string): string | null {
    return this.formCheque.get(campo)?.errors?.['backend'] ?? null;
  }

  guardarCheque(p: PagoServicio): void {
    if (!this.puedeGuardarCheque) {
      this.formCheque.markAllAsTouched();
      return;
    }

    const v = this.formCheque.value;
    this.guardandoCheque = true;

    this.service.actualizarCheque(this.detalle.sse_id, p.spa_id, {
      tipo: v.tipo,
      banco: v.banco.trim(),
      nombre: v.nombre.trim(),
      nrodoc: v.nrodoc.trim(),
      nro: v.nro.trim(),
      fecha_cobro: this.chequeEsDiferido ? v.fecha_cobro : null,
    }).subscribe({
      next: (res) => {
        this.guardandoCheque = false;
        this.toastr.success(res.message, 'Cheque actualizado');
        this.cancelarEdicion();
        this.guardado.emit(res.data);
      },
      error: (err) => {
        this.guardandoCheque = false;
        const errores = err?.error?.errors as Record<string, string[]> | undefined;
        if (errores) {
          Object.entries(errores).forEach(([campo, m]) => this.formCheque.get(campo)?.setErrors({ backend: m[0] }));
        }
        this.toastr.error(CobrosServicioService.mensajeError(err, 'No se pudo actualizar el cheque'), 'Error');
      },
    });
  }

  // ---------- Descartar ----------

  abrirDescartar(p: PagoServicio): void {
    this.pagoSeleccionado = p;
    this.motivoDescarte = '';
    this.descartarVisible = true;
  }

  confirmarDescarte(): void {
    const p = this.pagoSeleccionado;
    if (!p || this.descartando) return;

    this.descartando = true;

    this.service.descartarCheque(this.detalle.sse_id, p.spa_id, {
      confirma: true,
      motivo: this.motivoDescarte.trim() || null,
    }).subscribe({
      next: (res) => {
        this.descartando = false;
        this.descartarVisible = false;
        this.toastr.success(res.message, 'Cheque descartado');
        this.guardado.emit(res.data);

        // Queda listo para cargar los datos del papel que sí trajo.
        const actualizado = res.data.pagos.find((x) => x.spa_id === p.spa_id);
        if (actualizado) this.iniciarEdicion(actualizado);
      },
      error: (err) => {
        this.descartando = false;
        this.toastr.error(CobrosServicioService.mensajeError(err, 'No se pudo descartar el cheque'), 'Error');
      },
    });
  }

  // ---------- Modales ----------

  abrirRecibir(p: PagoServicio): void {
    this.cancelarEdicion();
    this.pagoSeleccionado = p;
    this.recibirVisible = true;
  }

  abrirRechazar(p: PagoServicio): void {
    this.cancelarEdicion();
    this.pagoSeleccionado = p;
    this.rechazarVisible = true;
  }

  formatoGs(valor: number): string {
    return new Intl.NumberFormat('es-PY', { maximumFractionDigits: 0 }).format(valor ?? 0);
  }
}