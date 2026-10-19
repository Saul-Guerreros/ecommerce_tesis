import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { AlertModule, ButtonModule, FormModule, GridModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { FuncionarioService } from '../service/funcionario.service';
import { BusquedaPersona, Funcionario, FuncionarioConfig, FuncionarioPayload } from '../models/funcionario.model';
import {FECHA_NACIMIENTO_MIN, errorFechaNacimiento, fechaNacimientoMax, hoyIso,} from '../../../core/validators/fecha-nacimiento';

@Component({
  selector: 'app-funcionario-form',
  standalone: true,
  imports: [CommonModule, FormsModule, ModalModule, GridModule, FormModule, ButtonModule, AlertModule, SpinnerModule],
  templateUrl: './funcionario-form.component.html',
})
export class FuncionarioFormComponent implements OnChanges {

  @Input() visible = false;
  @Input() funcionario: Funcionario | null = null;
  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<{ data: Funcionario; isEdit: boolean }>();

  form!: FuncionarioPayload;
  errors: any = {};
  submitting = false;
  buscando = false;
  personaEncontrada = false;

  /** Límites del input de fecha de nacimiento (desde 1940 hasta ayer). */
  readonly fechaMin = FECHA_NACIMIENTO_MIN;
  readonly fechaMax = fechaNacimientoMax();

  config: FuncionarioConfig = {
  tipos_documento: [], cargos: [], roles: [], estados_civiles: [], tipos_contacto: [],
	};



  constructor(
    private service: FuncionarioService,
    private toastr: ToastrService,
  ) {
    this.reset();
    this.cargarConfig();
  }

  get isEdit(): boolean {
    return !!this.funcionario?.fun_id;
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      this.reset();
      if (this.isEdit) {
        this.cargarFuncionario();
        this.asegurarCargoActual();
		 this.asegurarRolActual();
      }
    }
  }

 private cargarConfig(): void {
  this.service.config().subscribe({
    next: (c: FuncionarioConfig) => {
      this.config = c;
      this.asegurarCargoActual();
      this.asegurarRolActual();
    },
    error: () => this.toastr.error('No se pudieron cargar los datos del formulario'),
  });
}

  /** El catálogo solo trae cargos activos: si el funcionario tiene uno inactivo, se agrega para no perderlo al editar. */
  private asegurarCargoActual(): void {
    const actual = this.funcionario?.cargo;
    if (actual && !this.config.cargos.some(c => c.crg_id === actual.crg_id)) {
      this.config = { ...this.config, cargos: [...this.config.cargos, actual] };
    }
  }

/** El catálogo solo trae roles activos: si el funcionario tiene uno inactivo, se agrega para no perderlo al editar. */
private asegurarRolActual(): void {
  const f = this.funcionario;
  if (f?.rol_id && !this.config.roles.some(r => r.rol_id === f.rol_id)) {
    this.config = {
      ...this.config,
      roles: [...this.config.roles, { rol_id: f.rol_id, rol_descri: f.rol ?? `Rol #${f.rol_id}` }],
    };
  }
}

  reset(): void {
    this.form = {
      per_tpdid: null, per_cedula: '', per_nombr1: '', per_nombr2: null,
      per_apell1: '', per_apell2: null, per_estciv: null, per_fechna: null,
	  fun_crgid: null, rol_id: null, fun_fecing: hoyIso(), fun_fecegr: null, fun_estado: 1,
      contactos: [{ con_tipo: 'CELULAR', con_valor: '' }],
    };
    this.errors = {};
    this.personaEncontrada = false;
    this.submitting = false;
  }

  private cargarFuncionario(): void {
    const f = this.funcionario!;
    this.form = {
      per_id: f.persona.per_id,
      per_tpdid: f.persona.per_tpdid,
      per_cedula: f.persona.per_cedula,
      per_nombr1: f.persona.per_nombr1,
      per_nombr2: f.persona.per_nombr2,
      per_apell1: f.persona.per_apell1,
      per_apell2: f.persona.per_apell2,
      per_estciv: f.persona.per_estciv,
      per_fechna: f.persona.per_fechna,
      fun_crgid: f.cargo?.crg_id ?? null,
	  rol_id: f.rol_id ?? null,
      fun_fecing: f.fun_fecing,
      fun_fecegr: f.fun_fecegr,
      fun_estado: f.fun_estado,
      contactos: f.contactos?.length
        ? f.contactos.map(x => ({ con_tipo: x.con_tipo, con_valor: x.con_valor }))
        : [{ con_tipo: 'CELULAR', con_valor: '' }],
    };
  }

  // --- Contactos ---

  agregarContacto(): void {
    this.form.contactos.push({ con_tipo: 'CELULAR', con_valor: '' });
  }

  quitarContacto(i: number): void {
    if (this.form.contactos.length === 1) {
      this.toastr.warning('Debe mantener al menos un contacto');
      return;
    }
    this.form.contactos.splice(i, 1);
    this.limpiarErroresContacto();
  }

  /** Los índices se corren al borrar una fila; se limpian para no mostrar errores desfasados */
  private limpiarErroresContacto(): void {
    Object.keys(this.errors)
      .filter(k => k.startsWith('contactos'))
      .forEach(k => delete this.errors[k]);
  }

  errorContacto(i: number, campo: string): string | null {
    return this.errors[`contactos.${i}.${campo}`]?.[0] ?? null;
  }

  get errorContactosGeneral(): string | null {
    return this.errors['contactos']?.[0] ?? null;
  }

  // --- Flujo híbrido ---

  buscarPersona(): void {
    if (this.isEdit || !this.form.per_tpdid || !this.form.per_cedula) { return; }

    this.buscando = true;
    this.service.buscarPersona(this.form.per_tpdid, this.form.per_cedula).subscribe({
      next: (resp: BusquedaPersona) => {
        this.buscando = false;

        if (!resp.existe) { this.personaEncontrada = false; return; }

        if (resp.es_funcionario) {
          this.errors = { per_cedula: ['Esta persona ya está registrada como funcionario.'] };
          this.personaEncontrada = false;
          return;
        }

        const p = resp.persona!;
        this.form = {
          ...this.form,
          per_id: p.per_id,
          per_nombr1: p.per_nombr1, per_nombr2: p.per_nombr2,
          per_apell1: p.per_apell1, per_apell2: p.per_apell2,
          per_estciv: p.per_estciv, per_fechna: p.per_fechna,
          contactos: resp.contactos?.length
            ? resp.contactos.map(c => ({ con_tipo: c.con_tipo, con_valor: c.con_valor }))
            : this.form.contactos,
        };
        this.personaEncontrada = true;
        this.toastr.info('La persona ya existe en el sistema. Se completaron sus datos.');
      },
      error: () => { this.buscando = false; },
    });
  }

  // --- Guardado ---

  /** Los inputs de fecha vacíos llegan como '' : se normalizan a null antes de enviar. */
  private payload(): FuncionarioPayload {
    return {
      ...this.form,
      per_nombr2: this.form.per_nombr2 || null,
      per_apell2: this.form.per_apell2 || null,
      per_fechna: this.form.per_fechna || null,
      fun_fecegr: this.form.fun_fecegr || null,
    };
  }

  guardar(): void {
    if (this.submitting) { return; }

    // Validación local antes de llamar al backend (mismo formato de errores que el 422)
    const errorFecha = errorFechaNacimiento(this.form.per_fechna);
    if (errorFecha) {
      this.errors = { per_fechna: [errorFecha] };
      return;
    }
    if (this.form.fun_fecegr && this.form.fun_fecing && this.form.fun_fecegr < this.form.fun_fecing) {
      this.errors = { fun_fecegr: ['La fecha de egreso no puede ser anterior a la de ingreso.'] };
      return;
    }
	if (!this.form.rol_id) {
	  this.errors = { rol_id: ['Debe seleccionar el rol.'] };
	  return;
	}

    this.submitting = true;
    this.errors = {};

    const req$ = this.isEdit
      ? this.service.update(this.funcionario!.fun_id, this.payload())
      : this.service.store(this.payload());

    req$.subscribe({
      next: (resp) => {
        this.submitting = false;
        this.toastr.success(this.isEdit
          ? 'Funcionario actualizado correctamente'
          : 'Funcionario registrado correctamente', 'Éxito');
        this.guardado.emit({ data: resp.data, isEdit: this.isEdit });
        this.cerrar();
      },
      error: (err) => this.handleError(err),
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }

  private handleError(err: any): void {
    this.submitting = false;
    if (err.status === 422) {
      this.errors = err.error.errors;
      this.toastr.warning('Revise los campos marcados en rojo', 'Validación');
      return;
    }
    this.toastr.error(err.error?.message ?? 'Ocurrió un error inesperado', 'Error');
  }
}
