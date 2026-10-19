import { Component, DestroyRef, EventEmitter, Input, OnChanges, OnInit, Output, SimpleChanges, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormArray, FormBuilder, FormControl, FormGroup, FormsModule, ReactiveFormsModule, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, debounceTime, distinctUntilChanged, switchMap, of, catchError } from 'rxjs';
import { BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import {
  LineaPayload, OrdenConfig, OrdenTrabajo, ProductoBusqueda, TipoLinea, TrabajoOpcion,
} from '../models/orden-tecnico.model';
import { OrdenesTecnicoService } from '../service/ordenes-tecnico.service';

/**
 * T3 - Presupuesto. Mano de obra desde `trabajo` y repuestos desde `producto`.
 * El stock se muestra como CONSULTA (regla 1): presupuestar no reserva nada.
 * Los totales de pantalla son una vista previa: el servidor los recalcula.
 */
@Component({
  selector: 'app-form-presupuesto',
  standalone: true,
  imports: [CommonModule, FormsModule, ReactiveFormsModule, BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule],
  templateUrl: './form-presupuesto.component.html',
})
export class FormPresupuestoComponent implements OnInit, OnChanges {
  private readonly destroyRef = inject(DestroyRef);

  @Input({ required: true }) orden!: OrdenTrabajo;
  @Input({ required: true }) config!: OrdenConfig;
  @Output() actualizada = new EventEmitter<OrdenTrabajo>();

  form: FormGroup;
  guardando = false;
  enviando = false;

  trabajoElegido: number | null = null;
  buscar = new FormControl('', { nonNullable: true });
  resultados: ProductoBusqueda[] = [];
  buscando = false;
  listaAbierta = false;
  buscado = false;
  deposito: string | null = null;

  constructor(private fb: FormBuilder, private service: OrdenesTecnicoService, private toastr: ToastrService) {
    this.form = this.fb.group({
      lineas: this.fb.array([]),
      pse_obs: ['', Validators.maxLength(500)],
    });
  }

  get lineas(): FormArray<FormGroup> {
    return this.form.get('lineas') as FormArray<FormGroup>;
  }

  get esGarantia(): boolean {
    return this.orden?.sse_garant;
  }

  ngOnInit(): void {
    this.buscar.valueChanges.pipe(
      debounceTime(400),
      distinctUntilChanged(),
      switchMap((texto) => this.consultar(texto.trim())),
      takeUntilDestroyed(this.destroyRef),
    ).subscribe((res) => {
      this.resultados = res.data;
      this.deposito = res.meta.deposito ?? this.deposito;
      this.buscando = false;
      this.buscado = true;
    });
  }

  /** El catchError va DENTRO del switchMap: un error HTTP no debe cerrar el buscador. */
  private consultar(texto: string) {
    if (texto.length === 1) return of({ data: [] as ProductoBusqueda[], meta: { deposito: this.deposito } });
    this.buscando = true;
    return this.service.buscarProductos(this.orden.sse_id, texto).pipe(
      catchError((err) => {
        this.toastr.error(err?.error?.message || 'No se pudieron cargar los repuestos.', 'Repuestos');
        return of({ data: [] as ProductoBusqueda[], meta: { deposito: this.deposito } });
      }),
    );
  }

  /** Al enfocar con el campo vacío se listan los repuestos con stock en el taller. */
  abrirLista(): void {
    this.listaAbierta = true;
    if (!this.buscado && !this.buscar.value.trim()) {
      this.consultar('').subscribe((res) => {
        this.resultados = res.data;
        this.deposito = res.meta.deposito ?? this.deposito;
        this.buscando = false;
        this.buscado = true;
      });
    }
  }

  /** Retraso para que el click sobre un resultado se procese antes de ocultar la lista. */
  cerrarLista(): void {
    setTimeout(() => (this.listaAbierta = false), 200);
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['orden'] && this.orden) this.cargarDesdeOrden();
  }

  /** Puebla el FormArray con el borrador guardado (o vacío). */
  private cargarDesdeOrden(): void {
    this.lineas.clear();
    const p = this.orden.presupuesto;
    p?.detalles.forEach((d) => this.lineas.push(this.crearLinea({
      tipo: d.psd_tipo, prd_id: d.psd_prdid, trb_id: d.psd_trbid, descri: d.psd_descri,
      cantid: d.psd_cantid, precio: d.psd_precio, precio_lista: d.precio_lista ?? null,
      disponible: d.disponible ?? null, tasa: d.psd_total > 0 ? Math.round((d.psd_iva * 100) / (d.psd_total - d.psd_iva)) : 0,
    })));
    this.form.patchValue({ pse_obs: p?.pse_obs ?? '' });
    this.form.markAsPristine();
  }

  private crearLinea(v: {
    tipo: TipoLinea; prd_id?: number | null; trb_id?: number | null; descri: string; cantid: number;
    precio: number | null; precio_lista: number | null; disponible: number | null; tasa: number;
  }): FormGroup {
    return this.fb.group({
      tipo: [v.tipo],
      prd_id: [v.prd_id ?? null],
      trb_id: [v.trb_id ?? null],
      descri: [v.descri, [Validators.required, Validators.maxLength(150)]],
      cantid: [v.cantid, [Validators.required, Validators.min(0.0001), Validators.max(9999)]],
      precio: [{ value: this.esGarantia ? 0 : v.precio, disabled: this.esGarantia }, [Validators.required, Validators.min(0)]],
      // Solo de referencia (no viajan al backend)
      precio_lista: [v.precio_lista],
      disponible: [v.disponible],
      tasa: [v.tasa],
    });
  }

  // ---------- Agregar líneas ----------

  agregarTrabajo(): void {
    const t: TrabajoOpcion | undefined = this.config.trabajos.find((x) => x.trb_id === this.trabajoElegido);
    if (!t) return;
    this.lineas.push(this.crearLinea({
      tipo: 'MANO_OBRA', trb_id: t.trb_id, descri: t.trb_descri, cantid: 1,
      precio: t.trb_precio, precio_lista: t.trb_precio, disponible: null, tasa: t.tasa_iva,
    }));
    this.trabajoElegido = null;
    this.form.markAsDirty();
  }

  agregarProducto(p: ProductoBusqueda): void {
    if (this.lineas.controls.some((g) => g.value.prd_id === p.prd_id)) {
      this.toastr.info('Ese repuesto ya está en el presupuesto: ajuste la cantidad.', 'Presupuesto');
      return;
    }
    if (p.precio === null && !this.esGarantia) {
      this.toastr.warning('El producto no tiene precio de venta: cárguelo manualmente.', 'Presupuesto');
    }
    this.lineas.push(this.crearLinea({
      tipo: 'REPUESTO', prd_id: p.prd_id, descri: p.descri, cantid: 1, precio: p.precio,
      precio_lista: p.precio, disponible: p.controla ? p.disponible : null, tasa: p.tasa_iva,
    }));
    this.buscar.setValue('');
    this.resultados = [];
    this.form.markAsDirty();
  }

  quitarLinea(i: number): void {
    this.lineas.removeAt(i);
    this.form.markAsDirty();
  }

  // ---------- Totales (vista previa) ----------

  totalLinea(g: FormGroup): number {
    const v = g.getRawValue();
    return Math.round((Number(v.cantid) || 0) * (Number(v.precio) || 0));
  }

  get total(): number {
    return this.lineas.controls.reduce((acc, g) => acc + this.totalLinea(g), 0);
  }

  get iva(): number {
    return this.lineas.controls.reduce((acc, g) => {
      const tasa = Number(g.value.tasa) || 0;
      return acc + (tasa > 0 ? Math.round((this.totalLinea(g) * tasa) / (100 + tasa)) : 0);
    }, 0);
  }

  sinStock(g: FormGroup): boolean {
    const v = g.getRawValue();
    return v.tipo === 'REPUESTO' && v.disponible !== null && Number(v.cantid) > Number(v.disponible);
  }

  difiereDeLista(g: FormGroup): boolean {
    const v = g.getRawValue();
    return !this.esGarantia && v.precio_lista !== null && Number(v.precio) !== Number(v.precio_lista);
  }

  // ---------- Guardar / enviar ----------

  private payload(): { lineas: LineaPayload[]; pse_obs?: string } {
    return {
      pse_obs: this.form.value.pse_obs || undefined,
      lineas: this.lineas.controls.map((g) => {
        const v = g.getRawValue();
        return {
          tipo: v.tipo, prd_id: v.prd_id, trb_id: v.trb_id, descri: v.descri,
          cantid: Number(v.cantid), precio: this.esGarantia ? 0 : Number(v.precio),
        };
      }),
    };
  }

  guardar(despues?: () => void): void {
    if (this.lineas.length === 0) {
      this.toastr.warning('Agregue al menos una línea.', 'Presupuesto');
      return;
    }
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.toastr.warning('Revise las líneas marcadas.', 'Presupuesto');
      return;
    }

    this.guardando = true;
    this.service.guardarPresupuesto(this.orden.sse_id, this.payload()).subscribe({
      next: (res) => {
        this.guardando = false;
        this.form.markAsPristine();
        if (despues) { this.orden = res.data; despues(); return; }
        this.toastr.success(res.message, 'Éxito');
        this.actualizada.emit(res.data);
      },
      error: (err) => { this.guardando = false; this.mostrarError(err, 'No se pudo guardar el presupuesto'); },
    });
  }

  /** Si hay cambios sin guardar, primero guarda y después envía. */
  enviar(): void {
    const ejecutar = () => {
      this.enviando = true;
      this.service.enviarPresupuesto(this.orden.sse_id).subscribe({
        next: (res) => { this.enviando = false; this.toastr.success(res.message, 'Éxito'); this.actualizada.emit(res.data); },
        error: (err) => { this.enviando = false; this.mostrarError(err, 'No se pudo enviar el presupuesto'); },
      });
    };

    if (this.form.dirty || !this.orden.presupuesto) this.guardar(ejecutar);
    else ejecutar();
  }

  private mostrarError(err: any, porDefecto: string): void {
    const errores = err?.error?.errors as Record<string, string[]> | undefined;
    if (errores) {
      Object.entries(errores).forEach(([campo, msgs]) => {
        const m = campo.match(/^lineas\.(\d+)\.(\w+)$/);
        const control = m ? this.lineas.at(+m[1])?.get(m[2]) : null;
        if (control) { control.setErrors({ backend: msgs[0] }); control.markAsTouched(); }
        else this.toastr.error(msgs[0], 'Validación');
      });
      return;
    }
    this.toastr.error(err?.error?.message ?? porDefecto, 'Error');
  }
}
