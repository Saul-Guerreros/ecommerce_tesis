import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import { AlertModule, BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { FaqTienda, TemaFaq } from '../models/faq-tienda.model';
import { FaqTiendaService } from '../service/faq-tienda.service';
import { FormFaqTiendaComponent } from '../form-faq-tienda/form-faq-tienda.component';
import { DeleteFaqTiendaComponent } from '../delete-faq-tienda/delete-faq-tienda.component';

@Component({
  selector: 'app-list-faq-tienda',
  standalone: true,
  imports: [CommonModule, FormsModule, CardModule, TableModule, ButtonModule, BadgeModule, SpinnerModule,
    FormModule, AlertModule, FormFaqTiendaComponent, DeleteFaqTiendaComponent],
  templateUrl: './list-faq-tienda.component.html',
})
export class ListFaqTiendaComponent implements OnInit {
  faqs: FaqTienda[] = [];
  temas: TemaFaq[] = [];
  caracteresActivos = 0;
  cargando = false;

  search = '';
  filtroTema = '';
  filtroEstado = '';
  private searchSubject = new Subject<string>();

  formModalVisible = false;
  deleteModalVisible = false;
  faqSeleccionada: FaqTienda | null = null;

  constructor(private service: FaqTiendaService, private toastr: ToastrService) {}

  ngOnInit(): void {
    this.service.temas().subscribe({ next: (temas) => (this.temas = temas) });
    this.cargar();
    this.searchSubject.pipe(debounceTime(400), distinctUntilChanged()).subscribe(() => this.cargar());
  }

  /** Aproximación habitual: 1 token ≈ 4 caracteres en español. */
  get tokensAproximados(): number {
    return Math.ceil(this.caracteresActivos / 4);
  }

  onSearchChange(): void { this.searchSubject.next(this.search); }

  cargar(): void {
    this.cargando = true;
    this.service.getAll({ search: this.search, tema: this.filtroTema, estado: this.filtroEstado }).subscribe({
      next: (res) => {
        this.faqs = res.faqs;
        this.caracteresActivos = res.caracteresActivos;
        this.cargando = false;
      },
      error: () => { this.toastr.error('No se pudo cargar la información', 'Error'); this.cargando = false; },
    });
  }

  etiquetaTema(valor: string): string {
    return this.temas.find((t) => t.valor === valor)?.etiqueta ?? valor;
  }

  limpiarFiltros(): void { this.search = ''; this.filtroTema = ''; this.filtroEstado = ''; this.cargar(); }
  abrirNuevo(): void { this.faqSeleccionada = null; this.formModalVisible = true; }
  abrirEditar(f: FaqTienda): void { this.faqSeleccionada = { ...f }; this.formModalVisible = true; }
  abrirEliminar(f: FaqTienda): void { this.faqSeleccionada = f; this.deleteModalVisible = true; }

  confirmarEliminar(): void {
    if (!this.faqSeleccionada?.fqt_id) return;
    this.service.delete(this.faqSeleccionada.fqt_id).subscribe({
      next: (res) => { this.toastr.success(res?.message ?? 'Eliminada', 'Éxito'); this.deleteModalVisible = false; this.cargar(); },
      error: (err) => { this.toastr.error(err?.error?.message ?? 'No se pudo eliminar', 'Error'); this.deleteModalVisible = false; },
    });
  }

  cambiarEstado(f: FaqTienda): void {
    this.service.toggleEstado(f.fqt_id!).subscribe({
      next: (res) => { this.toastr.success(res?.message ?? 'Estado actualizado', 'Éxito'); this.cargar(); },
      error: (err) => this.toastr.error(err?.error?.message ?? 'No se pudo cambiar el estado', 'Error'),
    });
  }
}