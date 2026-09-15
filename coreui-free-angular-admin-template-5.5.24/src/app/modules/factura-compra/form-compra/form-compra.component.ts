import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule, FormBuilder, FormGroup, FormArray, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, CardModule, FormModule, SpinnerModule, TableModule, BadgeModule } from '@coreui/angular';
import {
  CompraCab, ProveedorOption, DepositoOption, TipoComprobanteOption, TipoIvaOption,
} from '../models/compra.model';
import { ComprasService } from '../service/compras.service';
import { ProductoSelect } from '../../productos/models/producto.model';
import { ProductosService } from '../../productos/service/productos.service';

@Component({
  selector: 'app-form-compra',
  standalone: true,
  imports: [CommonModule, FormsModule, ReactiveFormsModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule, BadgeModule],
  templateUrl: './form-compra.component.html',
})
export class FormCompraComponent implements OnInit {
  // ---- modo ----
  modoVer = false;
  cargando = false;
  guardando = false;
  compra: CompraCab | null = null; // solo se usa en modoVer

  // ---- combos ----
  proveedores: ProveedorOption[] = [];
  depositos: DepositoOption[] = [];
  tiposComprobante: TipoComprobanteOption[] = [];
  tiposIva: TipoIvaOption[] = [];
  productos: ProductoSelect[] = [];

  // ---- buscador de productos ----
  busquedaProducto = '';

  // ---- formulario (solo modo alta) ----
  formCab: FormGroup;
  erroresBackend: Record<string, string[]> | null = null;

  constructor(
    private fb: FormBuilder,
    private comprasService: ComprasService,
    private productosService: ProductosService,
    private route: ActivatedRoute,
    private router: Router,
    private toastr: ToastrService
  ) {
    this.formCab = this.fb.group({
      com_proid:  [null, Validators.required],
      com_depid:  [null, Validators.required],
      com_sucid:  [null, Validators.required], // se autocompleta al elegir deposito
      com_tcoid:  [null, Validators.required],
      com_timbra: [null, [Validators.maxLength(8)]],
      com_timbvn: [null],
      com_nrodoc: ['', [Validators.required, Validators.pattern(/^\d{3}-\d{3}-\d{7}$/)]],
      com_fchaem: ['', Validators.required],
      com_nrorem: [null],
      com_condic: [0, Validators.required],
      com_plzdia: [null],
      com_cuotas: [null],
      com_interv: [null],
      com_fchvto: [null],
      com_obs:    [null],
      detalle:    this.fb.array([]),
    });

    // com_cuotas es obligatorio SOLO si la condicion es Credito (1).
    // Mismo criterio que 'required_if' en CompraRequest del backend.
    this.formCab.get('com_condic')!.valueChanges.subscribe((valor) => {
      const cuotas = this.formCab.get('com_cuotas')!;
      if (Number(valor) === 1) {
        cuotas.setValidators([Validators.required, Validators.min(1)]);
      } else {
        cuotas.clearValidators();
        cuotas.setValue(null);
      }
      cuotas.updateValueAndValidity();
    });
  }

  ngOnInit(): void {
    const idParam = this.route.snapshot.paramMap.get('id');
    this.modoVer = !!idParam && idParam !== 'nueva';

    this.comprasService.getConfig().subscribe({
      next: (res) => {
        this.proveedores       = res.proveedores;
        this.depositos         = res.depositos;
        this.tiposComprobante  = res.tipos_comprobante;
        this.tiposIva          = res.tipos_iva;
      },
      error: () => this.toastr.error('No se pudo cargar la configuración', 'Error'),
    });

    if (this.modoVer) {
      this.cargando = true;
      this.comprasService.get(Number(idParam)).subscribe({
        next: (res) => { this.compra = res; this.cargando = false; },
        error: () => { this.toastr.error('No se pudo cargar la compra', 'Error'); this.cargando = false; },
      });
      return; // en modo ver no hace falta el buscador de productos
    }

    this.productosService.select().subscribe({
      next: (res) => (this.productos = res),
      error: () => this.toastr.error('No se pudieron cargar los productos', 'Error'),
    });
  }

  get detalleArray(): FormArray {
    return this.formCab.get('detalle') as FormArray;
  }

  // ---- deposito -> sucursal (autocompletado, no editable a mano) ----
  onDepositoChange(depId: string): void {
    const dep = this.depositos.find((d) => d.dpo_id === Number(depId));
    this.formCab.patchValue({ com_sucid: dep ? dep.dpo_sucid : null });
  }

  // ---- buscador de productos ----
  get productosFiltrados(): ProductoSelect[] {
    const q = this.busquedaProducto.trim().toLowerCase();
    if (!q) return [];
    return this.productos
      .filter((p) => p.prd_descri.toLowerCase().includes(q) || p.prd_codinv.toLowerCase().includes(q))
      .slice(0, 8);
  }

  agregarLinea(p: ProductoSelect): void {
    const yaExiste = this.detalleArray.controls.some((g) => g.value.prd_id === p.prd_id);
    if (yaExiste) {
      this.toastr.warning('Ese producto ya está en el detalle', 'Atención');
      return;
    }

    this.detalleArray.push(
      this.fb.group({
        prd_id: [p.prd_id, Validators.required],
        descri: [p.prd_descri],
        codigo: [p.prd_codinv],
        tiv_id: [p.prd_tivid, Validators.required],
        costo:  [null, [Validators.required, Validators.min(0.0001)]],
        canfac: [null, [Validators.required, Validators.min(0.0001)]],
        canrec: [null, [Validators.required, Validators.min(0)]],
        despor: [0],
        descto: [0],
      })
    );

    this.busquedaProducto = '';
  }

  quitarLinea(i: number): void {
    this.detalleArray.removeAt(i);
  }

  // Atajo para el caso normal (recepcion completa = lo facturado).
  // Si la entrega es parcial, el usuario edita canrec a mano despues.
  copiarCantidad(i: number): void {
    const g = this.detalleArray.at(i);
    g.patchValue({ canrec: g.value.canfac });
  }

  tasaDe(tivId: number | null): number {
    return this.tiposIva.find((t) => t.tiv_id === Number(tivId))?.tiv_tasa ?? 0;
  }

  /**
   * gravada/iva son TOTALES de la linea (costo x cantidad, con
   * descuento aplicado), no valores unitarios. Son el insumo que el
   * backend suma para armar com_grav5/com_grav10/com_iva5/com_iva10.
   */
  calcularLinea(v: any): { gravada: number; iva: number; total: number } {
    const costo  = Number(v.costo)  || 0;
    const canfac = Number(v.canfac) || 0;
    const despor = Number(v.despor) || 0;
    const descto = Number(v.descto) || 0;
    const tasa   = this.tasaDe(v.tiv_id);

    const subtotal     = costo * canfac;
    const conDescuento = subtotal * (1 - despor / 100) - descto;
    const gravada      = Math.max(conDescuento, 0);
    const iva          = gravada * (tasa / 100);

    return {
      gravada: this.round2(gravada),
      iva:     this.round2(iva),
      total:   this.round2(gravada + iva),
    };
  }

  private round2(n: number): number {
    return Math.round(n * 100) / 100;
  }

  // Totales de cabecera, calculados en vivo para mostrarle al usuario
  // ANTES de guardar. Es una previsualizacion: el valor que realmente
  // queda grabado lo recalcula el backend de forma independiente sobre
  // el mismo detalle (ver CompraService::calcularTotalesCabecera), asi
  // que aunque estas dos cuentas coincidan, nunca se manda com_total al
  // backend — el backend jamas confia en este numero.
  get totales() {
    let exenta = 0, grav5 = 0, grav10 = 0, iva5 = 0, iva10 = 0;

    for (const g of this.detalleArray.controls) {
      const v = g.value;
      const tasa = this.tasaDe(v.tiv_id);
      const { gravada, iva } = this.calcularLinea(v);

      if (tasa === 5)       { grav5  += gravada; iva5  += iva; }
      else if (tasa === 10) { grav10 += gravada; iva10 += iva; }
      else                  { exenta += gravada; }
    }

    return {
      exenta: this.round2(exenta),
      grav5: this.round2(grav5),
      grav10: this.round2(grav10),
      iva5: this.round2(iva5),
      iva10: this.round2(iva10),
      total: this.round2(exenta + grav5 + grav10 + iva5 + iva10),
    };
  }

  guardar(): void {
    if (this.detalleArray.length === 0) {
      this.toastr.warning('Agregue al menos un producto al detalle', 'Atención');
      return;
    }

    if (this.formCab.invalid) {
      this.formCab.markAllAsTouched();
      this.toastr.warning('Revise los campos de la cabecera', 'Atención');
      return;
    }

    this.guardando = true;
    this.erroresBackend = null;

    const detalle = this.detalleArray.controls.map((g) => {
      const v = g.value;
      const calc = this.calcularLinea(v);
      return {
        prd_id:  v.prd_id,
        tiv_id:  v.tiv_id,
        descri:  v.descri,
        costo:   v.costo,
        canfac:  v.canfac,
        canrec:  v.canrec,
        despor:  v.despor ?? 0,
        descto:  v.descto ?? 0,
        tasa:    this.tasaDe(v.tiv_id),
        gravada: calc.gravada,
        iva:     calc.iva,
      };
    });

    const { detalle: _omit, ...cabecera } = this.formCab.value;
    const payload = { ...cabecera, detalle };

    this.comprasService.create(payload).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Compra registrada correctamente', 'Éxito');
        this.guardando = false;
        this.router.navigate(['/compras']);
      },
      error: (err) => {
        this.guardando = false;
        if (err.status === 422 && err.error?.errors) {
          this.erroresBackend = err.error.errors;
          this.toastr.error('Revise los datos ingresados', 'Validación');
          return;
        }
        this.toastr.error(err?.error?.message ?? 'No se pudo registrar la compra', 'Error');
      },
    });
  }

  cancelar(): void {
    this.router.navigate(['/compras']);
  }
}