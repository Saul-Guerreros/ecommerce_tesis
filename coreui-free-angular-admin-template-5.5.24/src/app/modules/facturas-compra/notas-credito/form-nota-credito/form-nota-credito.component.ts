import { Component, EventEmitter, Input, Output, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormArray, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule, TableModule } from '@coreui/angular';
import { Compra } from '../../models/compra.model';
import { NotaCreditoConfig, LineaNCConfig } from '../models/nota-credito.model';
import { NotasCreditoService } from '../service/notas-credito.service';

@Component({
  selector: 'app-form-nota-credito',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule, TableModule],
  templateUrl: './form-nota-credito.component.html',
})
export class FormNotaCreditoComponent implements OnChanges {
  @Input() visible = false;
  @Input() compra: Compra | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  form: FormGroup;
  guardando = false;
  cargandoConfig = false;
  lineasDisponibles: LineaNCConfig[] = [];

  constructor(private fb: FormBuilder, private service: NotasCreditoService, private toastr: ToastrService) {
    this.form = this.fb.group({
      ncr_numero: ['', [Validators.required, Validators.maxLength(15)]],
      ncr_fecha:  ['', [Validators.required]],
      ncr_motivo: ['', [Validators.required, Validators.maxLength(100)]],
      ncr_obs:    [''],
      detalle:    this.fb.array([]),
    });
  }

  get detalle(): FormArray {
    return this.form.get('detalle') as FormArray;
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible && this.compra?.com_id) {
      this.guardando = false;
      this.form.reset({ ncr_numero: '', ncr_fecha: '', ncr_motivo: '', ncr_obs: '' });
      this.detalle.clear();
      this.cargarLineas();
    }
  }

  private cargarLineas(): void {
    this.cargandoConfig = true;
    this.service.getConfig(this.compra!.com_id!).subscribe({
      next: (res: NotaCreditoConfig) => {
        this.lineasDisponibles = res.lineas.filter((l) => l.saldo_acreditable > 0);
        this.lineasDisponibles.forEach((l) => this.detalle.push(this.filaDetalle(l)));
        this.cargandoConfig = false;
      },
      error: () => {
        this.toastr.error('No se pudo cargar el detalle de la compra', 'Error');
        this.cargandoConfig = false;
      },
    });
  }

  private filaDetalle(l: LineaNCConfig): FormGroup {
    return this.fb.group({
      cmd_id:            [l.cmd_id],
      incluir:           [false],
      cmd_descri:        [l.cmd_descri],
      cmd_tasa:          [l.cmd_tasa],
      cmd_precun:        [l.cmd_precun],
      saldo_acreditable: [l.saldo_acreditable],
      cantidad:          [null as number | null, [Validators.min(0)]],
      despor:            [null as number | null, [Validators.min(0), Validators.max(100)]],
      base_gravada:      [null as number | null, [Validators.min(0)]],
    });
  }

  /**
   * Descuento gravado = cmd_precun (precio de la factura) * cantidad * %.
   * Se dispara al tipear cantidad o %. El monto sigue siendo editable a
   * mano despues, por si la NC del proveedor viene con un importe fijo
   * en vez de un porcentaje.
   */
  recalcularDescuento(fila: any): void {
    const cantidad = Number(fila.value.cantidad) || 0;
    const despor   = Number(fila.value.despor) || 0;
    const precun   = Number(fila.value.cmd_precun) || 0;

    const monto = Math.round(precun * cantidad * despor / 100 * 100) / 100;
    fila.get('base_gravada')?.setValue(monto);
  }

  ivaLinea(fila: any): number {
    return Math.round((Number(fila.base_gravada) || 0) * (Number(fila.cmd_tasa) / 100) * 100) / 100;
  }

  get totalGravada(): number {
    return this.detalle.controls.filter((c) => c.value.incluir)
      .reduce((s, c) => s + (Number(c.value.base_gravada) || 0), 0);
  }

  get totalIva(): number {
    return this.detalle.controls.filter((c) => c.value.incluir)
      .reduce((s, c) => s + this.ivaLinea(c.value), 0);
  }

  get totalGeneral(): number {
    return this.totalGravada + this.totalIva;
  }

  guardar(): void {
    const seleccionadas = this.detalle.value.filter((l: any) => l.incluir);

    if (seleccionadas.length === 0) {
      this.toastr.error('Seleccione al menos un ítem a acreditar', 'Detalle vacío');
      return;
    }

    for (const l of seleccionadas) {
      if (!l.cantidad || l.cantidad <= 0 || l.cantidad > l.saldo_acreditable) {
        this.toastr.error(`Cantidad inválida en "${l.cmd_descri}" (máx. ${l.saldo_acreditable})`, 'Revise el detalle');
        return;
      }
      if (!l.base_gravada || l.base_gravada <= 0) {
        this.toastr.error(`Falta el importe del descuento en "${l.cmd_descri}"`, 'Revise el detalle');
        return;
      }
    }

    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.guardando = true;

    const payload = {
      ncr_numero: this.form.value.ncr_numero,
      ncr_fecha:  this.form.value.ncr_fecha,
      ncr_motivo: this.form.value.ncr_motivo,
      ncr_obs:    this.form.value.ncr_obs,
      detalle: seleccionadas.map((l: any) => ({
        cmd_id: l.cmd_id,
        cantidad: l.cantidad,
        base_gravada: l.base_gravada,
        iva: this.ivaLinea(l),
      })),
    };

    this.service.create(this.compra!.com_id!, payload).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Nota de Crédito registrada', 'Éxito');
        this.guardando = false;
        this.guardado.emit();
        this.cerrar();
      },
      error: (err) => {
        this.guardando = false;
        this.toastr.error(err?.error?.message ?? 'No se pudo registrar la Nota de Crédito', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}