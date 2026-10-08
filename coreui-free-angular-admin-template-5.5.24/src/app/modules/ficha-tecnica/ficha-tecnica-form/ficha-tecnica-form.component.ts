import { Component, Input, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { BadgeModule, FormModule, SpinnerModule } from '@coreui/angular';
import { CampoFicha, ValorFicha } from '../models/ficha-tecnica.model';
import { FichaTecnicaService } from '../service/ficha-tecnica.service';

/**
 * Formulario dinámico de la Ficha Técnica. Los campos salen de la
 * plantilla de la subcategoría elegida: una notebook muestra 7 campos y
 * un mouse 2, sin columnas fijas. No guarda por su cuenta: el componente
 * padre (Publicación Web) le pide los valores con valores() y los envía
 * en el mismo guardado de la publicación.
 *
 * Variantes: los datos se heredan del producto base y se muestran en
 * solo lectura; solo se edita el atributo propio (el que diferencia a la
 * variante, ej. Color).
 */
@Component({
  selector: 'app-ficha-tecnica-form',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink, BadgeModule, FormModule, SpinnerModule],
  templateUrl: './ficha-tecnica-form.component.html',
})
export class FichaTecnicaFormComponent implements OnChanges {
  @Input() productoId!: number;
  @Input() categoriaId: number | null = null;
  @Input() esVariante = false;
  /** Producto base de la variante: para el enlace "editar en el producto base". */
  @Input() productoBaseId: number | null = null;
  /** Errores 422 del backend, con clave "ficha.{atb_id}". */
  @Input() errores: Record<string, string> = {};

  campos: CampoFicha[] = [];
  cargando = false;
  errorCarga: string | null = null;
  /** true cuando los campos corresponden a la categoría actual (se puede enviar). */
  listo = false;
  /** Se activa al intentar publicar con obligatorios vacíos. */
  mostrarFaltantes = false;

  constructor(private fichaService: FichaTecnicaService) {}

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['categoriaId'] || changes['productoId'] || changes['esVariante']) {
      this.cargar();
    }
  }

  cargar(): void {
    this.listo = false;
    this.campos = [];
    this.errorCarga = null;
    this.mostrarFaltantes = false;

    if (!this.categoriaId || !this.productoId) return;

    this.cargando = true;
    this.fichaService.getCampos(this.productoId, this.categoriaId).subscribe({
      next: (campos) => {
        this.campos = campos.map((c) => ({ ...c, propiedad_ids: [...(c.propiedad_ids ?? [])] }));
        this.cargando = false;
        this.listo = true;
      },
      error: (err) => this.handleError(err),
    });
  }

  // ---- API para el componente padre ----

  /** Valores a enviar: solo los campos editables (en variantes, solo lo propio). */
  valores(): ValorFicha[] {
    return this.campos
      .filter((c) => c.editable)
      .map((c) => ({
        atb_id: c.atb_id,
        valor_texto: c.atb_tipo === 1 ? (c.valor_texto?.trim() || null) : null,
        valor_numero: c.atb_tipo === 2 && this.hayNumero(c) ? Number(c.valor_numero) : null,
        propiedad_ids: c.atb_tipo >= 3 ? c.propiedad_ids : [],
      }));
  }

  /** Obligatorios sin completar. En variantes, los heredados se corrigen en el base. */
  faltantes(): string[] {
    return this.campos
      .filter((c) => c.obligatorio && !this.tieneValor(c))
      .map((c) => (c.editable ? c.atb_nombre : `${c.atb_nombre} (en el producto base)`));
  }

  marcarFaltantes(): void {
    this.mostrarFaltantes = true;
  }

  // ---- Helpers del template ----

  get hayHeredados(): boolean {
    return this.campos.some((c) => c.origen === 'heredado');
  }

  error(c: CampoFicha): string | null {
    const backend = this.errores?.[`ficha.${c.atb_id}`];
    if (backend) return backend;
    if (!this.mostrarFaltantes || !c.obligatorio || this.tieneValor(c)) return null;
    return c.editable
      ? `${c.atb_nombre} es obligatorio para publicar.`
      : 'Falta en el producto base: completalo ahí.';
  }

  /** Texto de solo lectura para un campo heredado. */
  valorLegible(c: CampoFicha): string {
    switch (c.atb_tipo) {
      case 1:
        return c.valor_texto || '—';
      case 2:
        return this.hayNumero(c) ? `${c.valor_numero}${c.atb_unidad ? ' ' + c.atb_unidad : ''}` : '—';
      default:
        return c.propiedades.filter((p) => c.propiedad_ids.includes(p.prp_id)).map((p) => p.prp_nombre).join(', ') || '—';
    }
  }

  seleccionUnica(c: CampoFicha): number | null {
    return c.propiedad_ids[0] ?? null;
  }

  cambiarSeleccionUnica(c: CampoFicha, prpId: number | null): void {
    c.propiedad_ids = prpId ? [prpId] : [];
  }

  estaMarcada(c: CampoFicha, prpId: number): boolean {
    return c.propiedad_ids.includes(prpId);
  }

  alternarOpcion(c: CampoFicha, prpId: number, marcada: boolean): void {
    c.propiedad_ids = marcada
      ? [...c.propiedad_ids, prpId]
      : c.propiedad_ids.filter((id) => id !== prpId);
  }

  private hayNumero(c: CampoFicha): boolean {
    return c.valor_numero !== null && c.valor_numero !== undefined && (c.valor_numero as any) !== '';
  }

  private tieneValor(c: CampoFicha): boolean {
    switch (c.atb_tipo) {
      case 1: return !!c.valor_texto?.trim();
      case 2: return this.hayNumero(c);
      default: return c.propiedad_ids.length > 0;
    }
  }

  private handleError(err: any): void {
    this.cargando = false;
    this.errorCarga = err?.error?.message ?? 'No se pudo cargar la ficha técnica.';
  }
}
