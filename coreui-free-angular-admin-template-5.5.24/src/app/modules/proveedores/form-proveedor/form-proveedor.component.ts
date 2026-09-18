import { Component, EventEmitter, Input, Output, OnInit, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule, TableModule, BadgeModule } from '@coreui/angular';
import {
  Proveedor, TipoProveedorOption, CiudadOption, Contacto,
  TimbradoProveedor, TipoComprobanteOption,
} from '../models/proveedor.model';
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

    // --- Gestion de timbrados (sub-recurso) ---        ← PEGAR AQUÍ
  timbrados: TimbradoProveedor[] = [];
  tiposComprobante: TipoComprobanteOption[] = [];
  cargandoTimbrados = false;
  timbradoForm: FormGroup;
  editandoTimbradoId: number | null = null;
  guardandoTimbrado = false;
  timbradoAEliminar: TimbradoProveedor | null = null;
  private readonly hoy = this.fechaLocal();

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

    this.timbradoForm = this.fb.group({
      tpv_tcoid:  [null, [Validators.required]],
      tpv_numero: ['', [Validators.required, Validators.pattern(/^\d{1,8}$/)]],
      tpv_fechin: ['', [Validators.required]],
      tpv_fechfn: ['', [Validators.required]],
    });
  }

  ngOnInit(): void {
    this.service.getConfig().subscribe({
      next: (res) => { this.tipos = res.tipos; this.ciudades = res.ciudades; },
      error: () => this.toastr.error('No se pudieron cargar tipos de proveedor y ciudades', 'Error'),
    });

    this.service.getConfigTimbrados().subscribe({
      next: (res) => (this.tiposComprobante = res),
      error: () => this.toastr.error('No se pudieron cargar los tipos de comprobante', 'Error'),
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

      this.resetearFormTimbrado();
      this.timbradoAEliminar = null;

      // En modo Ver el formulario se deshabilita entero: bloquea la edicion
      // por teclado/paste aunque el usuario intente forzar los campos.
      this.soloLectura ? this.form.disable() : this.form.enable();

      if (this.esEdicion) {
        this.cargarContactos();
        this.cargarTimbrados();
      } else {
        this.contactos = [];
        this.timbrados = [];
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

    // ==================== TIMBRADOS ====================

  private fechaLocal(d = new Date()): string {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  }

  fmt(fecha?: string | null): string {
    if (!fecha) return '';
    const [y, m, d] = fecha.substring(0, 10).split('-');
    return `${d}/${m}/${y}`;
  }

  /** Estado visible: la vigencia se calcula contra hoy, tpv_estado es manual. */
  situacionTimbrado(t: TimbradoProveedor): { texto: string; color: string } {
    if (t.tpv_estado !== 1)      return { texto: 'Inactivo', color: 'secondary' };
    if (t.tpv_fechin > this.hoy) return { texto: 'Aún no vigente', color: 'info' };
    if (t.tpv_fechfn < this.hoy) return { texto: 'Vencido', color: 'warning' };
    return { texto: 'Vigente', color: 'success' };
  }

  timbradoEnUso(t: TimbradoProveedor): boolean {
    return (t.compras_count ?? 0) > 0;
  }

  campoTimbradoInvalido(campo: string): boolean {
    const c = this.timbradoForm.get(campo);
    return !!c && c.invalid && (c.touched || c.dirty);
  }

  errorBackendTimbrado(campo: string): string | null {
    return this.timbradoForm.get(campo)?.errors?.['backend'] ?? null;
  }

  private cargarTimbrados(): void {
    this.cargandoTimbrados = true;
    this.service.getTimbrados(this.proveedor!.pro_id!).subscribe({
      next: (res) => { this.timbrados = res; this.cargandoTimbrados = false; },
      error: () => { this.toastr.error('No se pudieron cargar los timbrados', 'Error'); this.cargandoTimbrados = false; },
    });
  }

  resetearFormTimbrado(): void {
    // Si hay un solo tipo (lo normal: Factura) se preselecciona.
    const tipoUnico = this.tiposComprobante.length === 1 ? this.tiposComprobante[0].tco_id : null;
    this.timbradoForm.reset({ tpv_tcoid: tipoUnico, tpv_numero: '', tpv_fechin: '', tpv_fechfn: '' });
    this.editandoTimbradoId = null;
  }

  editarTimbrado(t: TimbradoProveedor): void {
    this.editandoTimbradoId = t.tpv_id!;
    this.timbradoForm.patchValue({
      tpv_tcoid: t.tpv_tcoid, tpv_numero: t.tpv_numero, tpv_fechin: t.tpv_fechin, tpv_fechfn: t.tpv_fechfn,
    });
  }

  guardarTimbrado(): void {
    if (this.timbradoForm.invalid) {
      this.timbradoForm.markAllAsTouched();
      return;
    }

    const v = this.timbradoForm.value;
    if (v.tpv_fechfn < v.tpv_fechin) {
      this.timbradoForm.get('tpv_fechfn')?.setErrors({ backend: 'El vencimiento no puede ser anterior al inicio.' });
      this.timbradoForm.get('tpv_fechfn')?.markAsTouched();
      return;
    }

    this.guardandoTimbrado = true;
    const datos: Partial<TimbradoProveedor> = { ...v, tpv_proid: this.proveedor!.pro_id! };

    const peticion = this.editandoTimbradoId
      ? this.service.updateTimbrado(this.editandoTimbradoId, datos)
      : this.service.createTimbrado(datos);

    peticion.subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Timbrado guardado', 'Éxito');
        this.guardandoTimbrado = false;
        this.resetearFormTimbrado();
        this.cargarTimbrados();
      },
      error: (err) => {
        this.guardandoTimbrado = false;
        if (err.status === 422 && err.error?.errors) {
          Object.keys(err.error.errors).forEach((campo) => {
            this.timbradoForm.get(campo)?.setErrors({ backend: err.error.errors[campo][0] });
            this.timbradoForm.get(campo)?.markAsTouched();
          });
          this.toastr.error('Revise los datos del timbrado', 'Validación');
          return;
        }
        this.toastr.error(err?.error?.message ?? 'No se pudo guardar el timbrado', 'Error');
      },
    });
  }

  cambiarEstadoTimbrado(t: TimbradoProveedor): void {
    this.service.toggleEstadoTimbrado(t.tpv_id!).subscribe({
      next: (res) => { this.toastr.success(res?.message ?? 'Estado actualizado', 'Éxito'); this.cargarTimbrados(); },
      error: (err) => this.toastr.error(err?.error?.message ?? 'No se pudo cambiar el estado', 'Error'),
    });
  }

  pedirEliminarTimbrado(t: TimbradoProveedor): void {
    this.timbradoAEliminar = t;
  }

  cancelarEliminarTimbrado(): void {
    this.timbradoAEliminar = null;
  }

  confirmarEliminarTimbrado(): void {
    if (!this.timbradoAEliminar?.tpv_id) return;

    this.service.deleteTimbrado(this.timbradoAEliminar.tpv_id).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Timbrado eliminado', 'Éxito');
        this.timbradoAEliminar = null;
        if (this.editandoTimbradoId) this.resetearFormTimbrado();
        this.cargarTimbrados();
      },
      error: (err) => {
        this.toastr.error(err?.error?.message ?? 'No se pudo eliminar el timbrado', 'Error');
        this.timbradoAEliminar = null;
      },
    });
  }
}