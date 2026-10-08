import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, OnChanges, OnInit, Output, SimpleChanges } from '@angular/core';
import {
  AbstractControl, FormBuilder, FormGroup, ReactiveFormsModule, ValidationErrors, ValidatorFn, Validators,
} from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import {
  CiudadCatalogo, DepartamentoCatalogo, Direccion,
} from '../../../core/models/direccion.model';
import { DireccionClienteService } from '../../../core/services/direccion-cliente.service';

const ALIAS_SUGERIDOS = ['Mi Casa', 'Mi Trabajo', 'Otro'];

@Component({
  selector: 'app-form-direccion',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './form-direccion.component.html',
})
export class FormDireccionComponent implements OnInit, OnChanges {

  @Input() visible = false;
  @Input() direccion: Direccion | null = null;   // si viene, el modal edita

  /**
   * Direcciones ya guardadas del cliente. Se usan para validar en el
   * cliente que el alias no se repita, sin esperar el 422 del backend.
   * El padre (paso-entrega) ya las tiene cargadas, asi que no hace falta
   * un GET extra.
   */
  @Input() direcciones: Direccion[] = [];

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<Direccion>();

  form!: FormGroup;
  alias = ALIAS_SUGERIDOS;

  departamentos: DepartamentoCatalogo[] = [];
  private ciudades: CiudadCatalogo[] = [];
  ciudadesFiltradas: CiudadCatalogo[] = [];

  guardando = false;

  get esEdicion(): boolean {
    return !!this.direccion;
  }

  constructor(
    private fb: FormBuilder,
    private direccionService: DireccionClienteService,
    private toastr: ToastrService,
  ) {}

  ngOnInit(): void {
    this.form = this.fb.group({
      alias_opcion:    ['Mi Casa', [Validators.required]],
      dir_alias:       ['Mi Casa', [Validators.required, Validators.maxLength(50), this.aliasUnico()]],
      dep_id:          [null as number | null, [Validators.required]],
      dir_ciuid:       [null as number | null, [Validators.required]],
      dir_barrio:      ['', [Validators.required, Validators.maxLength(60)]],
      dir_calle:       ['', [Validators.required, Validators.maxLength(100)]],
      dir_nrocasa:     ['', [Validators.maxLength(45)]],
      dir_referencia:  ['', [Validators.maxLength(250)]],
      dir_predet:      [false],
    });

    this.direccionService.catalogos().subscribe({
      next: (resp) => {
        this.departamentos = resp.departamentos;
        this.ciudades = resp.ciudades;

        // Si el modal ya estaba visible en modo edición cuando llegó el
        // catálogo (carrera entre el HTTP y el Input), recién ahora se
        // puede resolver el departamento a partir de la ciudad.
        if (this.esEdicion) {
          this.precargarDesdeDireccion();
        }
      },
      error: () => this.toastr.error('No pudimos cargar los departamentos.', 'Error'),
    });
  }

  ngOnChanges(cambios: SimpleChanges): void {
    if (!this.form) {
      return;
    }

    if (cambios['visible']?.currentValue === true) {
      this.guardando = false;

      if (this.esEdicion) {
        this.precargarDesdeDireccion();
      } else {
        this.form.reset({ alias_opcion: 'Mi Casa', dir_alias: 'Mi Casa', dir_predet: false });
        this.ciudadesFiltradas = [];
        // Se recalcula la validez (el form sabe que es invalido y no
        // dejara guardar), pero SIN marcar touched: el modal no debe
        // abrirse mostrando un error que el usuario todavia no provoco.
        // El mensaje aparece recien al elegir una opcion o al enviar.
        this.revalidarAlias(false);
      }
    }

    // El alias que era valido puede dejar de serlo si el padre recarga
    // la lista (p. ej. tras guardar otra direccion con el modal abierto).
    if (cambios['direcciones'] && !cambios['direcciones'].firstChange) {
      this.revalidarAlias(false);
    }
  }

  // ---------------------------------------------------------------------
  // Validación de alias único
  // ---------------------------------------------------------------------

  /**
   * Normaliza para comparar: sin espacios sobrantes, sin mayusculas y
   * sin acentos. Espeja exactamente lo que hace normalizar_alias() en
   * PostgreSQL, para que front y back nunca den veredictos distintos.
   */
  private normalizar(valor: unknown): string {
    return String(valor ?? '')
      .trim()
      .replace(/\s+/g, ' ')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '');
  }

  /**
   * En edición se compara contra todas MENOS la que se está editando:
   * si no, la dirección chocaría consigo misma al guardar sin cambiar
   * el nombre.
   */
  private aliasDuplicado(valor: unknown): boolean {
    const objetivo = this.normalizar(valor);

    if (!objetivo) {
      return false;
    }

    return this.direcciones.some((d) =>
      d.dir_id !== this.direccion?.dir_id && this.normalizar(d.dir_alias) === objetivo);
  }

  /**
   * ValidatorFn que lee this.direcciones en cada ejecucion (no captura
   * la lista al construir el form), por eso sigue siendo correcto
   * aunque el @Input cambie despues.
   */
  private aliasUnico(): ValidatorFn {
    return (control: AbstractControl): ValidationErrors | null =>
      this.aliasDuplicado(control.value) ? { aliasDuplicado: true } : null;
  }

  private revalidarAlias(mostrarError: boolean): void {
    const control = this.form.get('dir_alias')!;
    control.updateValueAndValidity();

    if (mostrarError) {
      control.markAsTouched();
      control.markAsDirty();
    }
  }

  /** Marca en los botones los nombres que el cliente ya tiene usados. */
  aliasOcupado(opcion: string): boolean {
    return opcion !== 'Otro' && this.aliasDuplicado(opcion);
  }

  /** Un solo lugar decide qué mensaje mostrar bajo el campo. */
  get mensajeAlias(): string {
    const errores = this.form.get('dir_alias')?.errors ?? {};

    if (errores['backend'])       return errores['backend'];
    if (errores['aliasDuplicado']) return 'Ya tenés una dirección guardada con ese nombre. Elegí otro.';
    if (errores['maxlength'])      return 'El nombre no puede superar los 50 caracteres.';

    return 'Ponele un nombre a esta dirección.';
  }

  // ---------------------------------------------------------------------

  /**
   * Puebla el form a partir de la dirección a editar. La dirección solo
   * trae dir_ciuid (no dep_id, que es auxiliar de este modal); el
   * departamento se resuelve buscando esa ciudad en el catálogo ya
   * cargado, igual que hace direcciones.component.ts al editar.
   */
  private precargarDesdeDireccion(): void {
    if (!this.direccion || this.ciudades.length === 0) {
      return;
    }

    const ciudad = this.ciudades.find((c) => c.ciu_id === this.direccion!.dir_ciuid);
    const depId = ciudad?.ciu_depid ?? null;

    this.ciudadesFiltradas = depId
      ? this.ciudades.filter((c) => c.ciu_depid === depId)
      : [];

    this.form.reset({
      alias_opcion:   this.alias.includes(this.direccion.dir_alias) ? this.direccion.dir_alias : 'Otro',
      dir_alias:      this.direccion.dir_alias,
      dep_id:         depId,
      dir_ciuid:      this.direccion.dir_ciuid,
      dir_barrio:     this.direccion.dir_barrio,
      dir_calle:      this.direccion.dir_calle,
      dir_nrocasa:    this.direccion.dir_nrocasa ?? '',
      dir_referencia: this.direccion.dir_referencia ?? '',
      dir_predet:     this.direccion.dir_predet,
    });
  }

  get aliasEsOtro(): boolean {
    return this.form.value.alias_opcion === 'Otro';
  }

  cambiarAlias(opcion: string): void {
    this.form.patchValue({
      alias_opcion: opcion,
      dir_alias: opcion === 'Otro' ? '' : opcion,
    });

    // patchValue no marca el control como tocado, y campoInvalido()
    // exige dirty/touched para pintar el error. Sin esto, elegir un
    // nombre ya usado no mostraria ningun mensaje.
    const control = this.form.get('dir_alias')!;
    control.markAsDirty();
    control.markAsTouched();
  }

  cambiarDepartamento(): void {
    const dep = Number(this.form.value.dep_id);
    this.ciudadesFiltradas = this.ciudades.filter((c) => c.ciu_depid === dep);
    this.form.patchValue({ dir_ciuid: null });
  }

  campoInvalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && (c.dirty || c.touched);
  }

  errorBackend(campo: string): string | null {
    return this.form.get(campo)?.errors?.['backend'] ?? null;
  }

  guardar(): void {
    // Normaliza antes de validar y enviar: evita guardar "Mi  Casa " y
    // que el duplicado aparezca recien del lado del servidor.
    this.form.patchValue({
      dir_alias: String(this.form.value.dir_alias ?? '').trim().replace(/\s+/g, ' '),
    });

    this.form.markAllAsTouched();

    if (this.form.invalid || this.guardando) {
      return;
    }

    this.guardando = true;
    const { alias_opcion, dep_id, ...datos } = this.form.value;

    const peticion = this.esEdicion
      ? this.direccionService.actualizar(this.direccion!.dir_id, datos)
      : this.direccionService.crear(datos);

    peticion.subscribe({
      next: (resp) => {
        this.guardando = false;
        this.toastr.success(resp.message, 'Listo');
        this.guardado.emit(resp.data);
      },
      error: (e) => {
        this.guardando = false;

        if (e.status === 422 && e.error?.errors) {
          Object.entries<string[]>(e.error.errors).forEach(([campo, mensajes]) => {
            this.form.get(campo)?.setErrors({ backend: mensajes[0] });
            this.form.get(campo)?.markAsTouched();
          });
          return;
        }

        this.toastr.error(this.esEdicion ? 'No pudimos actualizar la dirección.' : 'No pudimos guardar la dirección.', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visibleChange.emit(false);
  }
}