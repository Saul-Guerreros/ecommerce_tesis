import { Component, EventEmitter, Input, Output, OnInit, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { ReactiveFormsModule, FormBuilder, FormGroup, FormArray, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, BadgeModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { Producto, MarcaOption, TipoIvaOption, UnidadMedidaOption } from '../models/producto.model';
import { ProductosService } from '../service/productos.service';
import { AtributoService } from '../../../views/atributos/services/atributo.service';
import { Atributo, Propiedad } from '../../../views/atributos/atributo.model';

import { EquivalenciaProductoComponent } from '../equivalencia-producto/equivalencia-producto.component';

@Component({
  selector: 'app-form-producto',
  standalone: true,
  imports: [CommonModule, RouterLink, ReactiveFormsModule, ButtonModule, BadgeModule, FormModule, ModalModule, SpinnerModule, EquivalenciaProductoComponent],
  templateUrl: './form-producto.component.html',
})
export class FormProductoComponent implements OnInit, OnChanges {
  @Input() visible = false;
  @Input() producto: Producto | null = null;
  @Input() soloLectura = false;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  form: FormGroup;
  guardando = false;

  marcas: MarcaOption[] = [];
  tiposIva: TipoIvaOption[] = [];
  unidades: UnidadMedidaOption[] = [];

  // Solo atributos "Seleccionable" (atb_tipo=3): son los que tienen un
  // conjunto fijo de Propiedades para elegir -- no tiene sentido ofrecer
  // acá un atributo de tipo Texto/Número/Selección múltiple (esos se
  // escriben libres, no eligen de una lista; ver el aparte que le debo a
  // Marcos sobre cómo se usa cada tipo).
  atributosSeleccionables: Atributo[] = [];

  // Sección "Variantes" (Opción 3: alta en lote). Es un toggle de UI, no
  // un control del form -- solo decide si se muestra la tabla de filas a
  // agregar. Arranca abierta si ya hay variantes que mostrar.
  seccionVariantesAbierta = false;
  errorVariantes: string | null = null;

  constructor(
    private fb: FormBuilder,
    private service: ProductosService,
    private atributoService: AtributoService,
    private toastr: ToastrService
  ) {
    // El costo, margen y precio de venta ya NO se muestran en este
    // formulario: viven enteramente en Gestión de Precios (minorista y
    // mayorista, con override por producto). Este form solo maneja los
    // datos propios del producto.
    this.form = this.fb.group({
      prd_marid:  [null, [Validators.required]],
      prd_tivid:  [null, [Validators.required]],
      prd_umdid:  [null, [Validators.required]],
      prd_codinv: ['', [Validators.required, Validators.maxLength(45)]],
      prd_descri: ['', [Validators.required, Validators.maxLength(150)]],
      prd_stkmin: [null, [Validators.required, Validators.min(0)]],
      prd_afestk: [1],
      prd_estado: [1],

      // Equivalencia inicial: solo se usa en modo creación (ver template).
      // En edición viaja siempre en false/null y el backend los ignora.
      tiene_equiv_compra: [false],
      peq_umdid:  [null],
      peq_factor: [null],

      // Alta en lote de variantes (Opción 3). Cada fila: ver
      // nuevaFilaVariante(). Se envía como "variantes" en el payload
      // (mapeado en guardar(), sin los campos auxiliares auto*).
      // Categoría NO se pide acá: la Publicación Web nace en borrador sin
      // categoría, y se completa cuando el admin entra a esa pantalla.
      variantes: this.fb.array([]),
    });

    // Alterna los validadores de peq_umdid/peq_factor segun tiene_equiv_compra.
    this.form.get('tiene_equiv_compra')!.valueChanges.subscribe((tiene) => {
      this.actualizarValidadoresEquivalencia(!!tiene);
    });
  }

  private actualizarValidadoresEquivalencia(tiene: boolean): void {
    const umd = this.form.get('peq_umdid')!;
    const factor = this.form.get('peq_factor')!;

    if (tiene) {
      umd.setValidators([Validators.required]);
      factor.setValidators([Validators.required, Validators.min(0.0001)]);
    } else {
      umd.clearValidators();
      factor.clearValidators();
      umd.setValue(null);
      factor.setValue(null);
    }
    umd.updateValueAndValidity({ emitEvent: false });
    factor.updateValueAndValidity({ emitEvent: false });
  }

  // Unidades ofrecibles como alternativa: todo el catálogo MENOS la unidad
  // de stock ya elegida en prd_umdid (misma regla que valida el backend).
  get unidadesAlternativasDisponibles(): UnidadMedidaOption[] {
    const base = this.form.value.prd_umdid;
    return this.unidades.filter((u) => u.umd_id !== base);
  }

  ngOnInit(): void {
    this.service.getConfig().subscribe({
      next: (res) => {
        this.marcas   = res.marcas;
        this.tiposIva = res.tipos_iva;
        this.unidades = res.unidades;
      },
      error: () => this.toastr.error('No se pudieron cargar marcas, IVA y unidades', 'Error'),
    });

    // page=1 sin search trae los primeros 25 (paginado fijo del backend,
    // ver AtributoController::index) -- suficiente para el uso normal de
    // un selector; si algún día hay más de 25 atributos "Seleccionable"
    // conviene sumarle un buscador acá, igual que tiene el listado propio.
    this.atributoService.list(1, '').subscribe({
      next: (res) => {
        this.atributosSeleccionables = (res.atributos ?? []).filter((a: Atributo) => a.atb_tipo === 3);
      },
      error: () => {},
    });
  }

  get esEdicion(): boolean {
    return !!this.producto?.prd_id;
  }

  // Este producto ES una variante de otro (viene ya vinculado desde que
  // se creó, vía el lote del padre) -- no se elige acá, es solo lectura.
  get esVariante(): boolean {
    return !!this.producto?.padre;
  }

  // Un producto que ya es (o va a ser) variante de otro no puede, a su
  // vez, tener variantes propias -- por eso la sección ni se muestra.
  get puedeTenerVariantes(): boolean {
    return !this.esVariante;
  }

  variantesArray(): FormArray {
    return this.form.get('variantes') as FormArray;
  }

  private nuevaFilaVariante() {
    return this.fb.group({
      // Solo ayuda a sugerir código/nombre -- no viaja al backend.
      valor:      ['', [Validators.required]],
      prd_descri: ['', [Validators.required, Validators.maxLength(150)]],
      prd_codinv: ['', [Validators.required, Validators.maxLength(45)]],
      prd_stkmin: [this.form.value.prd_stkmin ?? null, [Validators.required, Validators.min(0)]],
      // Por qué se diferencia ESTA fila puntual (cada una puede elegir un
      // atributo distinto: una variante por RAM, otra por color, en el
      // mismo lote). Siempre obligatorios -- si es una variante, por
      // definición se diferencia por algo.
      atb_id: [null as number | null, [Validators.required]],
      prp_id: [null as number | null, [Validators.required]],
      // Mientras el admin no toque código/nombre a mano en ESTA fila,
      // se le siguen actualizando solos al cambiar "valor".
      autoDescri: [true],
      autoCodinv: [true],
    });
  }

  // Propiedades del atributo elegido EN ESA FILA -- cada fila filtra las
  // suyas propias, no hay un atributo único para todo el lote.
  propiedadesDeFila(i: number): Propiedad[] {
    const atbId = this.variantesArray().at(i).value.atb_id;
    return this.atributosSeleccionables.find((a) => a.atb_id === atbId)?.propiedades ?? [];
  }

  // Al cambiar el atributo de una fila, su propiedad elegida hasta ahora
  // casi seguro ya no pertenece al nuevo atributo -- se limpia para no
  // dejar una combinación inválida sin que el admin lo note.
  onAtributoFilaChange(i: number): void {
    this.variantesArray().at(i).get('prp_id')!.setValue(null);
  }

  agregarVariante(): void {
    this.variantesArray().push(this.nuevaFilaVariante());
  }

  quitarVariante(i: number): void {
    this.variantesArray().removeAt(i);
  }

  toggleSeccionVariantes(): void {
    this.seccionVariantesAbierta = !this.seccionVariantesAbierta;
    if (this.seccionVariantesAbierta && this.variantesArray().length === 0) {
      this.agregarVariante();
    }
  }

  // Ej: escribís "16GB" en la fila -> sugiere "NB-X-16GB" / "Notebook X - 16GB",
  // tomando como base el código/nombre que ya cargaste arriba para el padre.
  actualizarSugerencia(i: number): void {
    const fila = this.variantesArray().at(i);
    const valor = (fila.value.valor || '').trim();
    if (!valor) {
      return;
    }

    const codinvBase = (this.form.value.prd_codinv || '').trim();
    const descriBase = (this.form.value.prd_descri || '').trim();
    const slug = valor.toUpperCase().replace(/[^A-Z0-9]+/g, '-').replace(/^-+|-+$/g, '');

    if (fila.value.autoCodinv) {
      fila.get('prd_codinv')!.setValue(codinvBase ? `${codinvBase}-${slug}` : slug, { emitEvent: false });
    }
    if (fila.value.autoDescri) {
      fila.get('prd_descri')!.setValue(descriBase ? `${descriBase} - ${valor}` : valor, { emitEvent: false });
    }
  }

  // Si el admin edita código o nombre a mano en una fila, se deja de
  // autosugerir ESA fila puntual (para no pisarle lo que ya ajustó).
  marcarEdicionManual(i: number, campo: 'prd_codinv' | 'prd_descri'): void {
    const flag = campo === 'prd_codinv' ? 'autoCodinv' : 'autoDescri';
    this.variantesArray().at(i).get(flag)!.setValue(false, { emitEvent: false });
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      this.form.reset({
        prd_marid:  this.producto?.prd_marid ?? null,
        prd_tivid:  this.producto?.prd_tivid ?? null,
        prd_umdid:  this.producto?.prd_umdid ?? null,
        prd_codinv: this.producto?.prd_codinv ?? '',
        prd_descri: this.producto?.prd_descri ?? '',
        prd_stkmin: this.producto?.prd_stkmin ?? null,
        prd_afestk: this.producto?.prd_afestk ?? 1,
        prd_estado: this.producto?.prd_estado ?? 1,

        // Solo aplica al crear: siempre arranca en "No" (requisito 2),
        // y en edición no se usa (el CRUD de equivalencias ya lo maneja).
        tiene_equiv_compra: false,
        peq_umdid:  null,
        peq_factor: null,
      });
      this.actualizarValidadoresEquivalencia(false);

      this.variantesArray().clear();
      this.errorVariantes = null;
      this.seccionVariantesAbierta = !!this.producto?.variantes?.length;

      this.guardando = false;

      if (this.soloLectura) {
        this.form.disable();
      } else {
        this.form.enable();
        // Marca/IVA/unidad de una variante existente son solo lectura: se
        // sincronizan solas desde el padre (ver ProductoController),
        // editarlas acá individualmente no tendría efecto real.
        if (this.esVariante) {
          this.form.get('prd_marid')!.disable();
          this.form.get('prd_tivid')!.disable();
          this.form.get('prd_umdid')!.disable();
        }
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

  filaCampoInvalido(i: number, campo: string): boolean {
    const c = this.variantesArray().at(i).get(campo);
    return !!c && c.invalid && (c.touched || c.dirty);
  }

  filaErrorBackend(i: number, campo: string): string | null {
    return this.variantesArray().at(i).get(campo)?.errors?.['backend'] ?? null;
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.guardando = true;
    this.errorVariantes = null;

    const { variantes, ...datos } = this.form.getRawValue();
    const payload: any = { ...datos };

    if (this.puedeTenerVariantes && this.seccionVariantesAbierta && Array.isArray(variantes) && variantes.length > 0) {
      payload['variantes'] = variantes.map((v: any) => ({
        prd_descri: v.prd_descri,
        prd_codinv: v.prd_codinv,
        prd_stkmin: v.prd_stkmin,
        atb_id: v.atb_id,
        prp_id: v.prp_id,
      }));
    }

    const peticion = this.esEdicion
      ? this.service.update(this.producto!.prd_id!, payload)
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
            const mensaje = err.error.errors[campo][0];
            const filaMatch = campo.match(/^variantes\.(\d+)\.(.+)$/);

            if (filaMatch) {
              const fila = this.variantesArray().at(+filaMatch[1]);
              fila?.get(filaMatch[2])?.setErrors({ backend: mensaje });
              fila?.get(filaMatch[2])?.markAsTouched();
            } else if (campo === 'variantes') {
              this.errorVariantes = mensaje;
            } else {
              this.form.get(campo)?.setErrors({ backend: mensaje });
              this.form.get(campo)?.markAsTouched();
            }
          });
          this.toastr.error('Revise los datos ingresados', 'Validación');
          return;
        }
        this.toastr.error(err?.error?.message ?? 'No se pudo guardar el producto', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}
