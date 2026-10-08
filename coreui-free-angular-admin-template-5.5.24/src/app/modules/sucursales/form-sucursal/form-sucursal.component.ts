import { Component, EventEmitter, Input, Output, OnInit, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { Sucursal, EmpresaOption, CiudadOption } from '../models/sucursal.model';
import { SucursalesService } from '../service/sucursales.service';

@Component({
  selector: 'app-form-sucursal',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './form-sucursal.component.html',
})
export class FormSucursalComponent implements OnInit, OnChanges {
  @Input() visible = false;
  @Input() sucursal: Sucursal | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  form: FormGroup;
  guardando = false;

  empresas: EmpresaOption[] = [];
  ciudades: CiudadOption[] = [];

  constructor(
    private fb: FormBuilder,
    private sucursalesService: SucursalesService,
    private toastr: ToastrService
  ) {
    this.form = this.fb.group({
      suc_empid:     [null, [Validators.required]],
      suc_ciuid:     [null, [Validators.required]],
      suc_nombre:    ['', [Validators.required, Validators.maxLength(50)]],
      suc_princi:    [0],
      suc_establ:    ['', [Validators.pattern(/^[0-9]{3}$/)]],
      suc_direccion: ['', [Validators.maxLength(100)]],
      suc_telef:     ['', [Validators.maxLength(20)]],
      suc_cel:       ['', [Validators.maxLength(20)]],
      suc_email:     ['', [Validators.email, Validators.maxLength(60)]],
      suc_estado:    [1],
      suc_retiro:    [false],                              // <-- nuevo
      suc_horario:   ['', [Validators.maxLength(150)]],     // <-- nuevo

    });
  }

  ngOnInit(): void {
    this.sucursalesService.getConfig().subscribe({
      next: (res) => {
        this.empresas = res.empresas;
        this.ciudades = res.ciudades;
      },
      error: () => this.toastr.error('No se pudieron cargar empresas y ciudades', 'Error'),
    });
  }

  get esEdicion(): boolean {
    return !!this.sucursal?.suc_id;
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
        this.form.reset({
        suc_empid:     this.sucursal?.suc_empid ?? (this.empresas.length === 1 ? this.empresas[0].emp_id : null),
        suc_ciuid:     this.sucursal?.suc_ciuid ?? null,
        suc_nombre:    this.sucursal?.suc_nombre ?? '',
        suc_princi:    this.sucursal?.suc_princi ?? 0,
        suc_establ:    this.sucursal?.suc_establ ?? '',
        suc_direccion: this.sucursal?.suc_direccion ?? '',
        suc_telef:     this.sucursal?.suc_telef ?? '',
        suc_cel:       this.sucursal?.suc_cel ?? '',
        suc_email:     this.sucursal?.suc_email ?? '',
        suc_estado:    this.sucursal?.suc_estado ?? 1,
        suc_retiro:    this.sucursal?.suc_retiro ?? false,      // <-- nuevo
        suc_horario:   this.sucursal?.suc_horario ?? '',        // <-- nuevo
      });
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
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.guardando = true;

        const datos: Partial<Sucursal> = {
      ...this.form.value,
      suc_establ:    this.form.value.suc_establ || null,
      suc_direccion: this.form.value.suc_direccion || null,
      suc_telef:     this.form.value.suc_telef || null,
      suc_cel:       this.form.value.suc_cel || null,
      suc_email:     this.form.value.suc_email || null,
      suc_horario:   this.form.value.suc_horario || null,   // <-- nuevo
    };

    const peticion = this.esEdicion
      ? this.sucursalesService.update(this.sucursal!.suc_id!, datos)
      : this.sucursalesService.create(datos);

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
            this.form.get(campo)?.setErrors({ backend: err.error.errors[campo][0] });
            this.form.get(campo)?.markAsTouched();
          });
          this.toastr.error('Revise los datos ingresados', 'Validación');
          return;
        }

        this.toastr.error(err?.error?.message ?? 'No se pudo guardar la sucursal', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}