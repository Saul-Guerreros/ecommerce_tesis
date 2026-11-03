import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import {
  BadgeModule, ButtonModule, CardModule, FormModule, ModalModule, SpinnerModule, TableModule,
} from '@coreui/angular';
import {
  EstadoOption, FeEstadoOption, MotivoNotaCredito, NotaCreditoVenta, SucursalOption,
} from '../models/nota-credito-venta.model';
import { NotasCreditoVentaService } from '../service/notas-credito-venta.service';
import { FormNotaCreditoVentaComponent } from '../form-nota-credito-venta/form-nota-credito-venta.component';
import { VerNotaCreditoVentaComponent } from '../ver-nota-credito-venta/ver-nota-credito-venta.component';

@Component({
  selector: 'app-list-nota-credito-venta',
  standalone: true,
  imports: [
    CommonModule, FormsModule, CardModule, TableModule, ButtonModule, BadgeModule,
    SpinnerModule, FormModule, ModalModule,
    FormNotaCreditoVentaComponent, VerNotaCreditoVentaComponent,
  ],
  templateUrl: './list-nota-credito-venta.component.html',
})
export class ListNotaCreditoVentaComponent implements OnInit {
  notas: NotaCreditoVenta[] = [];
  motivos: MotivoNotaCredito[] = [];
  estados: EstadoOption[] = [];
  feEstados: FeEstadoOption[] = [];
  sucursales: SucursalOption[] = [];

  cargando = false;
  cargandoVer = false;

  search = '';
  filtroEstado = '';
  filtroFe = '';
  filtroMotivo = '';
  filtroSucursal = '';
  filtroDesde = '';
  filtroHasta = '';

  private searchSubject = new Subject<string>();

  formVisible = false;
  verVisible = false;
  notaSeleccionada: NotaCreditoVenta | null = null;

  anularVisible = false;
  notaAAnular: NotaCreditoVenta | null = null;
  motivoAnulacion = '';
  anulando = false;

  // Ids con un reintento de NC electrónica en curso (evita doble clic).
  reintentando = new Set<number>();

  constructor(private service: NotasCreditoVentaService, private toastr: ToastrService) {}

  ngOnInit(): void {
    this.cargar();

    this.service.getConfig().subscribe({
      next: (res) => {
        this.motivos = res.motivos ?? [];
        this.estados = res.estados ?? [];
        this.feEstados = res.fe_estados ?? [];
        this.sucursales = res.sucursales ?? [];
      },
      error: () => {},
    });

    this.searchSubject
      .pipe(debounceTime(400), distinctUntilChanged())
      .subscribe(() => this.cargar());
  }

  onSearchChange(): void {
    this.searchSubject.next(this.search);
  }

  cargar(): void {
    this.cargando = true;

    this.service.getAll({
      search: this.search,
      estado: this.filtroEstado,
      fe_estado: this.filtroFe,
      motivo: this.filtroMotivo,
      sucursal: this.filtroSucursal,
      desde: this.filtroDesde,
      hasta: this.filtroHasta,
    }).subscribe({
      next: (res) => { this.notas = res; this.cargando = false; },
      error: () => {
        this.toastr.error('No se pudieron cargar las notas de crédito', 'Error');
        this.cargando = false;
      },
    });
  }

  limpiarFiltros(): void {
    this.search = '';
    this.filtroEstado = '';
    this.filtroFe = '';
    this.filtroMotivo = '';
    this.filtroSucursal = '';
    this.filtroDesde = '';
    this.filtroHasta = '';
    this.cargar();
  }

  abrirNueva(): void {
    this.formVisible = true;
  }

  /**
   * La NC nace electrónica: se graba como PENDIENTE y la declaración corre
   * en segundo plano. Se recarga enseguida y otra vez a los pocos segundos
   * para mostrar el resultado sin que el usuario tenga que refrescar.
   */
  alGuardar(): void {
    this.cargar();
    setTimeout(() => this.cargar(), 4000);
  }

  /** El listado no trae el detalle: se pide la NC completa al abrir el modal. */
  abrirVer(n: NotaCreditoVenta): void {
    this.cargandoVer = true;

    this.service.getById(n.ncv_id).subscribe({
      next: (completa) => {
        this.notaSeleccionada = completa;
        this.verVisible = true;
        this.cargandoVer = false;
      },
      error: () => {
        this.toastr.error('No se pudo cargar el detalle de la nota de crédito', 'Error');
        this.cargandoVer = false;
      },
    });
  }

  pedirAnular(n: NotaCreditoVenta): void {
    this.notaAAnular = n;
    this.motivoAnulacion = '';
    this.anularVisible = true;
  }

  cancelarAnular(): void {
    this.anularVisible = false;
    this.notaAAnular = null;
  }

  confirmarAnular(): void {
    if (!this.notaAAnular || this.anulando) return;

    this.anulando = true;

    this.service.anular(this.notaAAnular.ncv_id, this.motivoAnulacion).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Nota de crédito anulada', 'Éxito');
        this.anularVisible = false;
        this.verVisible = false;
        this.anulando = false;
        this.cargar();
      },
      error: (err) => {
        this.toastr.error(err?.error?.message ?? 'No se pudo anular la nota de crédito', 'Error');
        this.anulando = false;
      },
    });
  }

  // ---------- Nota de crédito electrónica ----------

  /** Abre el KuDE (PDF) en otra pestaña. Viaja con el JWT, por eso se pide como Blob. */
  abrirKude(n: NotaCreditoVenta): void {
    this.service.kude(n.ncv_id).subscribe({
      next: (blob) => this.service.abrirPdf(blob, `NC-${n.ncv_nrodoc}.pdf`),
      error: async (err) =>
        this.toastr.error(await this.service.mensajeDeError(err, 'No se pudo abrir el KuDE'), 'Error'),
    });
  }

  /** El XML es el documento con valor legal: se descarga, no se muestra. */
  descargarXml(n: NotaCreditoVenta): void {
    this.service.xml(n.ncv_id).subscribe({
      next: (blob) => this.service.descargar(blob, `NC-${n.ncv_nrodoc}.xml`),
      error: async (err) =>
        this.toastr.error(await this.service.mensajeDeError(err, 'No se pudo descargar el XML'), 'Error'),
    });
  }

  /**
   * Solo encola el reintento: la declaración corre en segundo plano. Se
   * recarga a los pocos segundos para mostrar el resultado.
   */
  reintentarFe(n: NotaCreditoVenta): void {
    if (this.reintentando.has(n.ncv_id)) return;

    this.reintentando.add(n.ncv_id);

    this.service.reintentarFe(n.ncv_id).subscribe({
      next: (res) => {
        this.toastr.info(res?.message ?? 'Reintento en curso', 'Nota de crédito electrónica');

        setTimeout(() => {
          this.reintentando.delete(n.ncv_id);
          this.cargar();
          this.refrescarVer(n.ncv_id);
        }, 4000);
      },
      error: (err) => {
        this.toastr.error(err?.error?.message ?? 'No se pudo reintentar', 'Error');
        this.reintentando.delete(n.ncv_id);
      },
    });
  }

  /** Si el modal Ver está abierto con esa NC, trae su estado actualizado. */
  private refrescarVer(id: number): void {
    if (!this.verVisible || this.notaSeleccionada?.ncv_id !== id) return;

    this.service.getById(id).subscribe({
      next: (completa) => (this.notaSeleccionada = completa),
      error: () => {},
    });
  }

  colorEstado(estado: number): string {
    return estado === 9 ? 'secondary' : 'success';
  }

  textoEstado(estado: number): string {
    return this.estados.find((e) => e.valor === estado)?.descripcion ?? '—';
  }

  colorFe(estado: number): string {
    if (estado === 2) return 'success';
    if (estado === 3) return 'danger';
    return 'warning';
  }

  textoFe(estado: number): string {
    if (estado === 2) return 'Enviada';
    if (estado === 3) return 'Rechazada';
    return 'Pendiente';
  }

  esPrueba(n: NotaCreditoVenta): boolean {
    return n.fe?.ambiente === 'simulado';
  }

  puedeReintentar(n: NotaCreditoVenta): boolean {
    return n.ncv_estado !== 9 && n.fe?.estado === 1;
  }

  /** Una NC ya declarada (o en proceso) no se anula en el sistema: la DNIT la tiene. */
  puedeAnular(n: NotaCreditoVenta): boolean {
    return n.ncv_estado !== 9 && !n.fe?.bloquea_anulacion;
  }

  identificacion(n: NotaCreditoVenta): string {
    return n.ncv_ruc ? `RUC ${n.ncv_ruc}` : `CI ${n.ncv_ci}`;
  }
}