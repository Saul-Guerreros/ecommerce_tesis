import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { PanelFila } from '../models/panel-servicio.model';
import { PanelServiciosService } from '../service/panel-servicios.service';

@Component({
  selector: 'app-reasignar-tecnico',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './reasignar-tecnico.component.html',
})
export class ReasignarTecnicoComponent implements OnChanges {
  @Input() visible = false;
  @Input() fila: PanelFila | null = null;
  @Input() tecnicos: { usu_id: number; nombre: string; abiertas?: number; sucursales?: number[] }[] = [];
  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  form: FormGroup;
  guardando = false;

  /** Cada sucursal tiene su taller: solo técnicos de la sucursal de la solicitud. */
  get tecnicosDisponibles() {
    const suc = this.fila?.sucursal?.suc_id ?? null;
    return suc ? this.tecnicos.filter((t) => (t.sucursales ?? []).includes(suc)) : this.tecnicos;
  }

  constructor(private fb: FormBuilder, private service: PanelServiciosService, private toastr: ToastrService) {
    this.form = this.fb.group({
      rse_tecid: [null as number | null, Validators.required],
      motivo: ['', [Validators.required, Validators.minLength(5), Validators.maxLength(200)]],
    });
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      this.form.reset({ rse_tecid: null, motivo: '' });
      this.guardando = false;
    }
  }

  campoInvalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && (c.touched || c.dirty);
  }

  errorBackend(campo: string): string | null {
    return this.form.get(campo)?.errors?.['backend'] ?? null;
  }

  guardar(): void {
    if (this.form.invalid || !this.fila) { this.form.markAllAsTouched(); return; }
    this.guardando = true;
    this.service.reasignar(this.fila.sse_id, this.form.value).subscribe({
      next: (res) => { this.guardando = false; this.toastr.success(res.message, 'Éxito'); this.guardado.emit(); this.cerrar(); },
      error: (err) => {
        this.guardando = false;
        if (err.status === 422 && err.error?.errors) {
          Object.entries(err.error.errors as Record<string, string[]>).forEach(([c, m]) => {
            this.form.get(c)?.setErrors({ backend: m[0] });
            this.form.get(c)?.markAsTouched();
          });
          return;
        }
        this.toastr.error(err?.error?.message ?? 'No se pudo reasignar', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}
