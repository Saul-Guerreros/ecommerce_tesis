import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { OrdenTrabajo } from '../models/orden-tecnico.model';
import { OrdenesTecnicoService } from '../service/ordenes-tecnico.service';

@Component({
  selector: 'app-cerrar-no-reparable',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './cerrar-no-reparable.component.html',
})
export class CerrarNoReparableComponent implements OnChanges {
  @Input() visible = false;
  @Input() orden: OrdenTrabajo | null = null;
  @Input() precioRevision: number | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<OrdenTrabajo>();

  form: FormGroup;
  guardando = false;

  constructor(private fb: FormBuilder, private service: OrdenesTecnicoService, private toastr: ToastrService) {
    this.form = this.fb.group({
      cobrar_revision: [false],
      ose_proced: ['', Validators.maxLength(2000)],
    });
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      this.form.reset({ cobrar_revision: false, ose_proced: this.orden?.diagnostico?.dia_causa ?? '' });
      // En garantía nunca se cobra la revisión (el backend también lo fuerza).
      if (this.orden?.sse_garant || this.precioRevision === null) this.form.get('cobrar_revision')!.disable();
      else this.form.get('cobrar_revision')!.enable();
      this.guardando = false;
    }
  }

  guardar(): void {
    if (this.form.invalid || !this.orden) return;
    this.guardando = true;

    this.service.cerrarNoReparable(this.orden.sse_id, this.form.getRawValue()).subscribe({
      next: (res) => {
        this.toastr.success(res.message, 'Éxito');
        this.guardando = false;
        this.guardado.emit(res.data);
        this.cerrar();
      },
      error: (err) => {
        this.guardando = false;
        const msg = err?.error?.errors ? Object.values(err.error.errors as Record<string, string[]>)[0]?.[0] : null;
        this.toastr.error(msg ?? err?.error?.message ?? 'No se pudo cerrar la orden', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}
