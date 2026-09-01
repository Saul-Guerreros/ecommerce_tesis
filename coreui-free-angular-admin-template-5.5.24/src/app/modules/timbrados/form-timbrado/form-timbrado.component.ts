import { Component, EventEmitter, Input, Output, OnInit, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import {
  Timbrado, EmpresaOption, SucursalOption, ComprobanteOption,
} from '../models/timbrado.model';
import { TimbradosService } from '../service/timbrados.service';

@Component({
  selector: 'app-form-timbrado',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './form-timbrado.component.html',
})
export class FormTimbradoComponent implements OnInit, OnChanges {
  @Input() visible = false;
  @Input() timbrado: Timbrado | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  form: FormGroup;
  guardando = false;

  empresas: EmpresaOption[] = [];
  sucursales: SucursalOption[] = [];
  comprobantes: ComprobanteOption[] = [];

  constructor(
    private fb: FormBuilder,
    private timbradosService: TimbradosService,
    private toastr: ToastrService
  ) {
    this.form = this.fb.group({
      tim_empid:    [null, [Validators.required]],
      tim_sucid:    [null, [Validators.required]],
      tim_tcoid:    [null, [Validators.required]],
      tim_numero:   ['', [Validators.required, Validators.pattern(/^[0-9]{8}$/)]],
      tim_establec: ['001', [Validators.required, Validators.pattern(/^[0-9]{3}$/)]],
      tim_puntexp:  ['001', [Validators.required, Validators.pattern(/^[0-9]{3}$/)]],
      tim_fechin:   [null, [Validators.required]],
      tim_fechfn:   [null, [Validators.required]],
      tim_nroini:   [1, [Validators.required, Validators.min(1)]],
      tim_nrofin:   [null, [Validators.required, Validators.min(1)]],
      tim_observacion: ['', [Validators.maxLength(150)]],
      tim_estado:   [1],
    });
  }

  ngOnInit(): void {
    this.timbradosService.getConfig().subscribe({
      next: (res) => {
        this.empresas = res.empresas;
        this.sucursales = res.sucursales;
        this.comprobantes = res.comprobantes;
      },
      error: () => this.toastr.error('No se pudieron cargar los datos del formulario', 'Error'),
    });

    // La sucursal depende de la empresa: al cambiarla se limpia la anterior
    // para no dejar una combinación inválida que el backend rechazaría.
    this.form.get('tim_empid')?.valueChanges.subscribe(() => {
      const actual = this.form.get('tim_sucid')?.value;

      if (actual && !this.sucursalesFiltradas.some((s) => s.suc_id === actual)) {
        this.form.get('tim_sucid')?.setValue(null);
      }
    });
  }

  get esEdicion(): boolean {
    return !!this.timbrado?.tim_id;
  }

  // Un talonario que ya emitió documentos no puede cambiar de identidad:
  // eso reescribiría la numeración de lo ya facturado.
  get enUso(): boolean {
    if (!this.timbrado?.tim_id) return false;
    const emitidos = (this.timbrado.tim_nroact ?? 0) - ((this.timbrado.tim_nroini ?? 1) - 1);
    return emitidos > 0;
  }

  get sucursalesFiltradas(): SucursalOption[] {
    const empresa = this.form.get('tim_empid')?.value;
    return empresa ? this.sucursales.filter((s) => s.suc_empid === empresa) : [];
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      this.form.reset({
        tim_empid:    this.timbrado?.tim_empid ?? (this.empresas.length === 1 ? this.empresas[0].emp_id : null),
        tim_sucid:    this.timbrado?.tim_sucid ?? null,
        tim_tcoid:    this.timbrado?.tim_tcoid ?? null,
        tim_numero:   this.timbrado?.tim_numero ?? '',
        tim_establec: this.timbrado?.tim_establec ?? '001',
        tim_puntexp:  this.timbrado?.tim_puntexp ?? '001',
        tim_fechin:   this.timbrado?.tim_fechin ?? null,
        tim_fechfn:   this.timbrado?.tim_fechfn ?? null,
        tim_nroini:   this.timbrado?.tim_nroini ?? 1,
        tim_nrofin:   this.timbrado?.tim_nrofin ?? null,
        tim_observacion: this.timbrado?.tim_observacion ?? '',
        tim_estado:   this.timbrado?.tim_estado ?? 1,
      });

      const inmutables = ['tim_empid', 'tim_sucid', 'tim_tcoid', 'tim_numero',
                          'tim_establec', 'tim_puntexp', 'tim_nroini'];

      inmutables.forEach((campo) => {
        this.enUso ? this.form.get(campo)?.disable() : this.form.get(campo)?.enable();
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

    // getRawValue: los campos bloqueados deben viajar igual para que el
    // backend los compare contra lo almacenado.
    const datos: Partial<Timbrado> = {
      ...this.form.getRawValue(),
      tim_observacion: this.form.getRawValue().tim_observacion || null,
    };

    const peticion = this.esEdicion
      ? this.timbradosService.update(this.timbrado!.tim_id!, datos)
      : this.timbradosService.create(datos);

    peticion.subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Operacion realizada', 'Exito');
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
          this.toastr.error('Revise los datos ingresados', 'Validacion');
          return;
        }

        this.toastr.error(err?.error?.message ?? 'No se pudo guardar el timbrado', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}
