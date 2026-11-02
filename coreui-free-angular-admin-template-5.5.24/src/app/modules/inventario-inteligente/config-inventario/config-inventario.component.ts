// modules/inventario-inteligente/config-inventario/config-inventario.component.ts
import { Component, EventEmitter, Input, OnInit, Output, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, GridModule, SpinnerModule } from '@coreui/angular';
import { InventarioInteligenteService } from '../service/inventario-inteligente.service';
import { ConfigInventario } from '../models/inventario-inteligente.model';

/** Parámetros del cálculo y recálculo manual. */
@Component({
  selector: 'app-config-inventario',
  standalone: true,
  imports: [CommonModule, FormsModule, ButtonModule, FormModule, GridModule, SpinnerModule],
  templateUrl: './config-inventario.component.html',
})
export class ConfigInventarioComponent implements OnInit {
  @Input() puedeGestionar = false;
  @Output() cambio = new EventEmitter<void>();

  private service = inject(InventarioInteligenteService);
  private toastr = inject(ToastrService);

  config: ConfigInventario = { nivel_servicio: 95, horizonte: 4, lead_time: 7, cobertura_max: 12 };
  guardando = false;
  recalculando = false;

  ngOnInit(): void {
    this.service.getConfig().subscribe({
      next: (c) => (this.config = c),
      error: () => this.toastr.error('No se pudo cargar la configuración', 'Error'),
    });
  }

  guardar(): void {
    this.guardando = true;
    const { factor_z, ...datos } = this.config;

    this.service.actualizarConfig(datos).subscribe({
      next: (res) => {
        this.config = res.data;
        this.toastr.success(res.message, 'Éxito');
        this.guardando = false;
        this.cambio.emit();
      },
      error: (err) => {
        const errores = err?.error?.errors ? Object.values(err.error.errors).flat().join(' ') : null;
        this.toastr.error(errores ?? err?.error?.message ?? 'No se pudo guardar', 'Error');
        this.guardando = false;
      },
    });
  }

  recalcular(): void {
    this.recalculando = true;
    this.service.recalcular().subscribe({
      next: (res) => {
        const a = res?.data?.alertas;
        this.toastr.success(a ? `${a.creadas} creadas · ${a.actualizadas} actualizadas · ${a.cerradas} cerradas` : res?.message,
          'Pronóstico recalculado');
        this.recalculando = false;
        this.cambio.emit();
      },
      error: (err) => {
        this.toastr.error(err?.status === 429 ? 'Esperá un minuto antes de volver a recalcular' : (err?.error?.message ?? 'Error al recalcular'), 'Error');
        this.recalculando = false;
      },
    });
  }
}
