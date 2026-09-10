import { Component, EventEmitter, Input, Output, OnInit, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { Rol, PermisoOption } from '../models/rol.model';
import { RolesService } from '../service/roles.service';

@Component({
  selector: 'app-form-rol',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './form-rol.component.html',
})
export class FormRolComponent implements OnInit, OnChanges {
  @Input() visible = false;
  @Input() rol: Rol | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  form: FormGroup;
  guardando = false;

  permisos: PermisoOption[] = [];
  permisosSeleccionados = new Set<number>();

  constructor(private fb: FormBuilder, private service: RolesService, private toastr: ToastrService) {
    this.form = this.fb.group({
      rol_descri: ['', [Validators.required, Validators.maxLength(45)]],
      rol_estado: [1],
    });
  }

  ngOnInit(): void {
    this.service.getConfig().subscribe({
      next: (res) => (this.permisos = res.permisos),
      error: () => this.toastr.error('No se pudieron cargar los permisos', 'Error'),
    });
  }

  get esEdicion(): boolean {
    return !!this.rol?.rol_id;
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      this.form.reset({
        rol_descri: this.rol?.rol_descri ?? '',
        rol_estado: this.rol?.rol_estado ?? 1,
      });
      this.permisosSeleccionados = new Set(this.rol?.permisos ?? []);
      this.guardando = false;
    }
  }

  estaSeleccionado(prmId: number): boolean {
    return this.permisosSeleccionados.has(prmId);
  }

  alternarPermiso(prmId: number, event: Event): void {
    const marcado = (event.target as HTMLInputElement).checked;
    if (marcado) {
      this.permisosSeleccionados.add(prmId);
    } else {
      this.permisosSeleccionados.delete(prmId);
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
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.guardando = true;

    const payload = {
      ...this.form.value,
      permisos: Array.from(this.permisosSeleccionados),
    };

    const peticion = this.esEdicion
      ? this.service.update(this.rol!.rol_id!, payload)
      : this.service.create(payload);

    peticion.subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Operación realizada', 'Éxito');
        this.guardando = false;
        this.guardado.emit();
        this.cerrar();
      },
      error: (err) => {
        this.guardando = false;
        if (err.status === 422 && err.error?.errors) {
          Object.keys(err.error.errors).forEach((campo) => {
            const base = campo.split('.')[0];
            this.form.get(base)?.setErrors({ backend: err.error.errors[campo][0] });
            this.form.get(base)?.markAsTouched();
          });
          this.toastr.error('Revise los datos ingresados', 'Validación');
          return;
        }
        this.toastr.error(err?.error?.message ?? 'No se pudo guardar el rol', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}