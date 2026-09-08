import { Component, EventEmitter, Input, Output, OnInit, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule, TableModule, BadgeModule } from '@coreui/angular';
import { Proveedor, TipoProveedorOption, CiudadOption, Contacto } from '../models/proveedor.model';
import { ProveedoresService } from '../service/proveedores.service';

/**
 * Modal unico para alta y edicion de proveedor. La gestion de contactos
 * (tabla + mini-formulario) vive DENTRO de este mismo componente y solo se
 * muestra en modo edicion, porque un contacto necesita un pro_id existente
 * (cpr_proid NOT NULL): no tiene sentido en modo alta hasta guardar primero
 * el proveedor.
 */
@Component({
  selector: 'app-form-proveedor',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule, TableModule, BadgeModule],
  templateUrl: './form-proveedor.component.html',
})
export class FormProveedorComponent implements OnInit, OnChanges {
  @Input() visible = false;
  @Input() proveedor: Proveedor | null = null;
  /**
   * Modo Ver: mismo modal, formulario deshabilitado, sin acciones de
   * guardar ni de gestion de contactos. Pensado para roles que solo
   * necesitan consultar el proveedor (ej. Ventas) una vez que el Modulo 1
   * de RBAC este activo.
   */
  @Input() soloLectura = false;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  form: FormGroup;
  guardando = false;

  tipos: TipoProveedorOption[] = [];
  ciudades: CiudadOption[] = [];

  // --- Gestion de contactos (sub-recurso) ---
  contactos: Contacto[] = [];
  cargandoContactos = false;
  contactoForm: FormGroup;
  editandoContactoId: number | null = null;
  guardandoContacto = false;
  contactoAEliminar: Contacto | null = null;

  constructor(
    private fb: FormBuilder,
    private service: ProveedoresService,
    private toastr: ToastrService
  ) {
    this.form = this.fb.group({
      pro_tprid:     [null, [Validators.required]],
      pro_ciuid:     [null, [Validators.required]],
      pro_ruc:       ['', [Validators.required, Validators.maxLength(15)]],
      pro_razon:     ['', [Validators.required, Validators.maxLength(150)]],
      pro_nomfan:    ['', [Validators.maxLength(80)]],
      pro_direccion: ['', [Validators.maxLength(100)]],
      pro_telef:     ['', [Validators.maxLength(20)]],
      pro_email:     ['', [Validators.email, Validators.maxLength(60)]],
      pro_estado:    [1],
    });

    this.contactoForm = this.fb.group({
      cpr_nombre:   ['', [Validators.required, Validators.maxLength(60)]],
      cpr_apellido: ['', [Validators.required, Validators.maxLength(60)]],
      cpr_cargo:    ['', [Validators.maxLength(40)]],
      cpr_telef:    ['', [Validators.maxLength(20)]],
      cpr_email:    ['', [Validators.email, Validators.maxLength(80)]],
      cpr_princi:   [0],
    });
  }

  ngOnInit(): void {
    this.service.getConfig().subscribe({
      next: (res) => { this.tipos = res.tipos; this.ciudades = res.ciudades; },
      error: () => this.toastr.error('No se pudieron cargar tipos de proveedor y ciudades', 'Error'),
    });
  }

  get esEdicion(): boolean {
    return !!this.proveedor?.pro_id;
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      this.form.reset({
        pro_tprid:     this.proveedor?.pro_tprid ?? null,
        pro_ciuid:     this.proveedor?.pro_ciuid ?? null,
        pro_ruc:       this.proveedor?.pro_ruc ?? '',
        pro_razon:     this.proveedor?.pro_razon ?? '',
        pro_nomfan:    this.proveedor?.pro_nomfan ?? '',
        pro_direccion: this.proveedor?.pro_direccion ?? '',
        pro_telef:     this.proveedor?.pro_telef ?? '',
        pro_email:     this.proveedor?.pro_email ?? '',
        pro_estado:    this.proveedor?.pro_estado ?? 1,
      });
      this.guardando = false;
      this.resetearFormContacto();

      // En modo Ver el formulario se deshabilita entero: bloquea la edicion
      // por teclado/paste aunque el usuario intente forzar los campos.
      this.soloLectura ? this.form.disable() : this.form.enable();

      if (this.esEdicion) {
        this.cargarContactos();
      } else {
        this.contactos = [];
      }
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

    const datos: Partial<Proveedor> = {
      ...this.form.value,
      pro_nomfan:    this.form.value.pro_nomfan || null,
      pro_direccion: this.form.value.pro_direccion || null,
      pro_telef:     this.form.value.pro_telef || null,
      pro_email:     this.form.value.pro_email || null,
    };

    const peticion = this.esEdicion
      ? this.service.update(this.proveedor!.pro_id!, datos)
      : this.service.create(datos);

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
        this.toastr.error(err?.error?.message ?? 'No se pudo guardar el proveedor', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }

  // ==================== CONTACTOS ====================

  private cargarContactos(): void {
    this.cargandoContactos = true;
    this.service.getContactos(this.proveedor!.pro_id!).subscribe({
      next: (res) => { this.contactos = res; this.cargandoContactos = false; },
      error: () => { this.toastr.error('No se pudieron cargar los contactos', 'Error'); this.cargandoContactos = false; },
    });
  }

  private resetearFormContacto(): void {
    this.contactoForm.reset({ cpr_nombre: '', cpr_apellido: '', cpr_cargo: '', cpr_telef: '', cpr_email: '', cpr_princi: 0 });
    this.editandoContactoId = null;
  }

  editarContacto(c: Contacto): void {
    this.editandoContactoId = c.cpr_id!;
    this.contactoForm.patchValue({
      cpr_nombre: c.cpr_nombre, cpr_apellido: c.cpr_apellido, cpr_cargo: c.cpr_cargo,
      cpr_telef: c.cpr_telef, cpr_email: c.cpr_email, cpr_princi: c.cpr_princi,
    });
  }

  guardarContacto(): void {
    if (this.contactoForm.invalid) {
      this.contactoForm.markAllAsTouched();
      return;
    }

    this.guardandoContacto = true;

    const datos: Partial<Contacto> = {
      ...this.contactoForm.value,
      cpr_proid: this.proveedor!.pro_id!,
      cpr_cargo: this.contactoForm.value.cpr_cargo || null,
      cpr_telef: this.contactoForm.value.cpr_telef || null,
      cpr_email: this.contactoForm.value.cpr_email || null,
    };

    const peticion = this.editandoContactoId
      ? this.service.updateContacto(this.editandoContactoId, datos)
      : this.service.createContacto(datos);

    peticion.subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Contacto guardado', 'Éxito');
        this.guardandoContacto = false;
        this.resetearFormContacto();
        this.cargarContactos();
      },
      error: (err) => {
        this.guardandoContacto = false;
        if (err.status === 422 && err.error?.errors) {
          Object.keys(err.error.errors).forEach((campo) => {
            this.contactoForm.get(campo)?.setErrors({ backend: err.error.errors[campo][0] });
          });
          this.toastr.error('Revise los datos del contacto', 'Validación');
          return;
        }
        this.toastr.error(err?.error?.message ?? 'No se pudo guardar el contacto', 'Error');
      },
    });
  }

  pedirEliminarContacto(c: Contacto): void {
    this.contactoAEliminar = c;
  }

  cancelarEliminarContacto(): void {
    this.contactoAEliminar = null;
  }

  confirmarEliminarContacto(): void {
    if (!this.contactoAEliminar?.cpr_id) return;

    this.service.deleteContacto(this.contactoAEliminar.cpr_id).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Contacto eliminado', 'Éxito');
        this.contactoAEliminar = null;
        this.cargarContactos();
      },
      error: (err) => {
        this.toastr.error(err?.error?.message ?? 'No se pudo eliminar el contacto', 'Error');
        this.contactoAEliminar = null;
      },
    });
  }
}