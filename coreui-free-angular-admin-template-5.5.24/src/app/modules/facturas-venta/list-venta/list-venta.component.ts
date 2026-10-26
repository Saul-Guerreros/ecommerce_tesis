import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import {
  BadgeModule, ButtonModule, CardModule, FormModule, ModalModule, SpinnerModule, TableModule,
} from '@coreui/angular';
import { EstadoOption, FeEstadoOption, Venta } from '../models/venta.model';
import { VentasService } from '../service/ventas.service';
import { VerVentaComponent } from '../ver-venta/ver-venta.component';

@Component({
  selector: 'app-list-venta',
  standalone: true,
  imports: [
    CommonModule, FormsModule, CardModule, TableModule, ButtonModule,
    BadgeModule, SpinnerModule, FormModule, ModalModule, VerVentaComponent,
  ],
  templateUrl: './list-venta.component.html',
})
export class ListVentaComponent implements OnInit {
  ventas: Venta[] = [];
  estados: EstadoOption[] = [];
  feEstados: FeEstadoOption[] = [];
  cargando = false;
  cargandoVer = false;

  search = '';
  filtroEstado = '';
  filtroFe = '';
  filtroDesde = '';
  filtroHasta = '';

  private searchSubject = new Subject<string>();

  verModalVisible = false;
  ventaSeleccionada: Venta | null = null;

  anularModalVisible = false;
  ventaAAnular: Venta | null = null;
  motivoAnulacion = '';
  anulando = false;

  // Ids con un reintento de factura electronica en curso (evita doble clic).
  reintentando = new Set<number>();

  constructor(private service: VentasService, private toastr: ToastrService) {}

  ngOnInit(): void {
    this.cargar();

    this.service.getConfig().subscribe({
      next: (res) => {
        this.estados = res.estados;
        this.feEstados = res.fe_estados ?? [];
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

    this.service
      .getAll({
        search: this.search,
        estado: this.filtroEstado,
        fe_estado: this.filtroFe,
        desde: this.filtroDesde,
        hasta: this.filtroHasta,
      })
      .subscribe({
        next: (res) => { this.ventas = res; this.cargando = false; },
        error: () => {
          this.toastr.error('No se pudieron cargar las facturas de venta', 'Error');
          this.cargando = false;
        },
      });
  }

  limpiarFiltros(): void {
    this.search = '';
    this.filtroEstado = '';
    this.filtroFe = '';
    this.filtroDesde = '';
    this.filtroHasta = '';
    this.cargar();
  }

  /**
   * El listado no trae el detalle (seria N+1 de lineas para nada): se
   * pide la factura completa recien al abrir el modal.
   */
  abrirVer(v: Venta): void {
    this.cargandoVer = true;

    this.service.getById(v.ven_id).subscribe({
      next: (completa) => {
        this.ventaSeleccionada = completa;
        this.verModalVisible = true;
        this.cargandoVer = false;
      },
      error: () => {
        this.toastr.error('No se pudo cargar el detalle de la factura', 'Error');
        this.cargandoVer = false;
      },
    });
  }

  // Se dispara desde el listado o desde el footer del modal Ver.
  pedirAnular(v: Venta): void {
    this.ventaAAnular = v;
    this.motivoAnulacion = '';
    this.anularModalVisible = true;
  }

  cancelarAnular(): void {
    this.anularModalVisible = false;
    this.ventaAAnular = null;
  }

  confirmarAnular(): void {
    if (!this.ventaAAnular || this.anulando) return;

    this.anulando = true;

    this.service.anular(this.ventaAAnular.ven_id, this.motivoAnulacion).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Factura anulada', 'Éxito');
        this.anularModalVisible = false;
        this.verModalVisible = false;
        this.anulando = false;
        this.cargar();
      },
      error: (err) => {
        this.toastr.error(err?.error?.message ?? 'No se pudo anular la factura', 'Error');
        this.anulando = false;
      },
    });
  }

  // ---------- Factura electronica ----------

  /** Abre el KuDE (PDF) en otra pestaña. Viaja con el JWT, por eso se pide como Blob. */
  abrirKude(v: Venta): void {
    this.service.kude(v.ven_id).subscribe({
      next: (blob) => this.service.abrirPdf(blob, `${v.ven_nrodoc}.pdf`),
      error: async (err) =>
        this.toastr.error(await this.service.mensajeDeError(err, 'No se pudo abrir el KuDE'), 'Error'),
    });
  }

  /** El XML es el documento con valor legal: se descarga, no se muestra. */
  descargarXml(v: Venta): void {
    this.service.xml(v.ven_id).subscribe({
      next: (blob) => this.service.descargar(blob, `${v.ven_nrodoc}.xml`),
      error: async (err) =>
        this.toastr.error(await this.service.mensajeDeError(err, 'No se pudo descargar el XML'), 'Error'),
    });
  }

  /**
   * Solo encola el reintento: la declaracion corre en segundo plano. Se
   * recarga a los pocos segundos para mostrar el resultado.
   */
  reintentarFe(v: Venta): void {
    if (this.reintentando.has(v.ven_id)) return;

    this.reintentando.add(v.ven_id);

    this.service.reintentarFe(v.ven_id).subscribe({
      next: (res) => {
        this.toastr.info(res?.message ?? 'Reintento en curso', 'Facturación electrónica');

        setTimeout(() => {
          this.reintentando.delete(v.ven_id);
          this.cargar();
          this.refrescarVer(v.ven_id);
        }, 4000);
      },
      error: (err) => {
        this.toastr.error(err?.error?.message ?? 'No se pudo reintentar', 'Error');
        this.reintentando.delete(v.ven_id);
      },
    });
  }

  /** Si el modal Ver esta abierto con esa factura, trae su estado actualizado. */
  private refrescarVer(id: number): void {
    if (!this.verModalVisible || this.ventaSeleccionada?.ven_id !== id) return;

    this.service.getById(id).subscribe({
      next: (completa) => (this.ventaSeleccionada = completa),
      error: () => {},
    });
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

  esPrueba(v: Venta): boolean {
    return v.fe?.ambiente === 'simulado';
  }

  puedeReintentar(v: Venta): boolean {
    return v.ven_estado !== 9 && v.fe?.estado === 1;
  }

  /**
   * Una factura aprobada ante la DNIT (o en proceso) no se anula desde aca:
   * se corrige con una nota de credito. El backend lo aplica igual; esto
   * solo evita mostrar un boton que siempre fallaria.
   */
  puedeAnular(v: Venta): boolean {
    return v.ven_estado !== 9 && !v.fe?.bloquea_anulacion;
  }

  colorEstado(estado: number): string {
    if (estado === 9) return 'secondary';
    if (estado === 2) return 'info';
    return 'success';
  }

  textoEstado(estado: number): string {
    const encontrado = this.estados.find((e) => e.valor === estado);
    return encontrado?.descripcion ?? '—';
  }

  identificacion(v: Venta): string {
    return v.ven_ruc ? `RUC ${v.ven_ruc}` : `CI ${v.ven_ci}`;
  }
}