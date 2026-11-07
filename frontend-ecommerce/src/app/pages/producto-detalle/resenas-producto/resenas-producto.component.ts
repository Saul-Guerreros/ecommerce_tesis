import { CommonModule, DatePipe } from '@angular/common';
import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import {
  EstadoMiResena, OrdenResenas, ResenaPublica, ResumenResenas,
} from '../../../core/models/resena.model';
import { ResenaService } from '../../../core/services/resena.service';
import { AuthService } from '../../auth/service/auth.service';

/**
 * Pestaña "Reseñas" del detalle de producto.
 *
 * Contenedor propio para no seguir agrandando ProductoDetalleComponent:
 * recibe el slug, carga sus datos y avisa al padre cuando cambia el
 * resumen (para actualizar las estrellas de la cabecera y el contador).
 */
@Component({
  selector: 'app-resenas-producto',
  standalone: true,
  imports: [CommonModule, DatePipe, ReactiveFormsModule, RouterModule],
  templateUrl: './resenas-producto.component.html',
  styleUrl: './resenas-producto.component.css',
})
export class ResenasProductoComponent implements OnChanges {
  readonly MAX_TITULO = 80;
  readonly MAX_COMENTARIO = 1000;
  readonly ESTRELLAS = [1, 2, 3, 4, 5];
  readonly ETIQUETAS = ['', 'Muy malo', 'Malo', 'Regular', 'Bueno', 'Excelente'];

  @Input({ required: true }) slug!: string;
  /** true cuando se llega desde "Calificar" en Mis Pedidos (?resena=1). */
  @Input() abrirFormulario = false;
  @Output() resumenCambio = new EventEmitter<ResumenResenas>();

  // --- Listado ---
  resenas: ResenaPublica[] = [];
  resumen: ResumenResenas = { promedio: null, total: 0, distribucion: {} };
  cargando = false;
  pagina = 1;
  ultimaPagina = 1;
  orden: OrdenResenas = 'recientes';
  filtroEstrellas: number | null = null;

  // --- Reseña propia ---
  estadoMia: EstadoMiResena | null = null;
  formVisible = false;
  guardando = false;
  eliminando = false;
  hover = 0;
  form: FormGroup;
  /** El formulario se abre solo una vez por ?resena=1, no en cada recarga. */
  private autoAbierto = false;

  constructor(
    private servicio: ResenaService,
    private auth: AuthService,
    private toastr: ToastrService,
    private fb: FormBuilder,
  ) {
    this.form = this.fb.group({
      res_califi: [0, [Validators.required, Validators.min(1), Validators.max(5)]],
      res_titulo: ['', [Validators.maxLength(this.MAX_TITULO)]],
      res_coment: ['', [Validators.required, Validators.minLength(10), Validators.maxLength(this.MAX_COMENTARIO)]],
    });
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['slug'] && this.slug) {
      this.orden = 'recientes';
      this.filtroEstrellas = null;
      this.formVisible = false;
      this.autoAbierto = false;
      this.cargar(1);
      this.cargarMia();
    }
  }

  // ---------------------------------------------------------------
  // Listado
  // ---------------------------------------------------------------

  cargar(pagina = this.pagina): void {
    this.cargando = true;

    this.servicio.listar(this.slug, pagina, this.orden, this.filtroEstrellas).subscribe({
      next: (res) => {
        this.resenas = res.data;
        this.pagina = res.meta.current_page;
        this.ultimaPagina = res.meta.last_page;
        this.resumen = res.resumen;
        this.cargando = false;
      },
      error: () => {
        this.cargando = false;
        this.toastr.error('No se pudieron cargar las reseñas.', 'Error');
      },
    });
  }

  cambiarOrden(orden: string): void {
    this.orden = orden as OrdenResenas;
    this.cargar(1);
  }

  /** Clic en una barra de la distribución: filtra (o quita el filtro). */
  filtrarPorEstrellas(estrellas: number): void {
    if (!this.resumen.distribucion[estrellas]) return;
    this.filtroEstrellas = this.filtroEstrellas === estrellas ? null : estrellas;
    this.cargar(1);
  }

  porcentaje(estrellas: number): number {
    const cantidad = this.resumen.distribucion[estrellas] ?? 0;
    return this.resumen.total > 0 ? Math.round((cantidad / this.resumen.total) * 100) : 0;
  }

  cambiarPagina(pagina: number): void {
    if (pagina < 1 || pagina > this.ultimaPagina || pagina === this.pagina) return;
    this.cargar(pagina);
  }

  get paginas(): number[] {
    return Array.from({ length: this.ultimaPagina }, (_, i) => i + 1);
  }

  llena(calificacion: number | null, posicion: number): boolean {
    return (calificacion ?? 0) >= posicion - 0.25;
  }

  // ---------------------------------------------------------------
  // Reseña propia
  // ---------------------------------------------------------------

  get conSesion(): boolean {
    return this.auth.isLoggedIn();
  }

  get loginUrl(): string {
    return `/producto/${this.slug}`;
  }

  private cargarMia(): void {
    this.estadoMia = null;
    if (!this.auth.isLoggedIn()) return;

    this.servicio.mia(this.slug).subscribe({
      next: (res) => {
        this.estadoMia = res.data;
        if (this.abrirFormulario && !this.autoAbierto && (res.data.puede_resenar || res.data.resena)) {
          this.autoAbierto = true;
          this.abrirForm();
        }
      },
      error: () => (this.estadoMia = null),
    });
  }

  abrirForm(): void {
    const mia = this.estadoMia?.resena;
    this.form.reset({
      res_califi: mia?.calificacion ?? 0,
      res_titulo: mia?.titulo ?? '',
      res_coment: mia?.comentario ?? '',
    });
    this.hover = 0;
    this.formVisible = true;
  }

  cancelarForm(): void {
    this.formVisible = false;
  }

  elegirEstrella(valor: number): void {
    this.form.get('res_califi')?.setValue(valor);
    this.form.get('res_califi')?.markAsTouched();
  }

  get calificacionElegida(): number {
    return this.form.get('res_califi')?.value ?? 0;
  }

  get largoComentario(): number {
    return (this.form.get('res_coment')?.value ?? '').length;
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

    const datos = {
      res_califi: Number(this.form.value.res_califi),
      res_titulo: (this.form.value.res_titulo ?? '').trim() || null,
      res_coment: (this.form.value.res_coment ?? '').trim(),
    };

    const mia = this.estadoMia?.resena;
    const peticion = mia ? this.servicio.actualizar(mia.id, datos) : this.servicio.crear(this.slug, datos);

    this.guardando = true;
    peticion.subscribe({
      next: (res) => {
        this.guardando = false;
        this.toastr.success(res.message, 'Reseña');
        this.estadoMia = { puede_resenar: false, motivo: null, resena: res.data };
        this.formVisible = false;
        this.resumenCambio.emit(res.resumen);
        this.cargar(1);
      },
      error: (err) => {
        this.guardando = false;
        if (err.status === 422 && err.error?.errors) {
          Object.keys(err.error.errors).forEach((campo) => {
            this.form.get(campo)?.setErrors({ backend: err.error.errors[campo][0] });
            this.form.get(campo)?.markAsTouched();
          });
          return;
        }
        this.toastr.error(err?.error?.message ?? 'No se pudo guardar tu reseña.', 'Error');
      },
    });
  }

  eliminar(): void {
    const mia = this.estadoMia?.resena;
    if (!mia || !confirm('¿Eliminar tu reseña? Esta acción no se puede deshacer.')) return;

    this.eliminando = true;
    this.servicio.eliminar(mia.id).subscribe({
      next: (res) => {
        this.eliminando = false;
        this.toastr.success(res.message, 'Reseña');
        this.resumenCambio.emit(res.resumen);
        this.cargarMia();
        this.cargar(1);
      },
      error: (err) => {
        this.eliminando = false;
        this.toastr.error(err?.error?.message ?? 'No se pudo eliminar tu reseña.', 'Error');
      },
    });
  }

  trackById(_: number, r: ResenaPublica): number {
    return r.id;
  }
}