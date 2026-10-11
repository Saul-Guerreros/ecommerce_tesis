import { Injectable } from '@angular/core';
import { ValorFicha } from '../../../modules/ficha-tecnica/models/ficha-tecnica.model';

export interface BorradorPublicacion {
  formValue: any;
  archivosNuevos: File[];
  previews: string[];
  ficha: ValorFicha[] | null;
}

/**
 * Guarda en memoria lo que el usuario estaba cargando en una publicación
 * cuando sale a otro módulo (ej. crear campaña) y lo restaura al volver.
 * En memoria porque los File no se pueden serializar a localStorage.
 */
@Injectable({ providedIn: 'root' })
export class PublicacionBorradorService {
  private borradores = new Map<number, BorradorPublicacion>();

  guardar(productoId: number, borrador: BorradorPublicacion): void {
    this.borradores.set(productoId, borrador);
  }

  /** Devuelve el borrador y lo elimina (se usa una sola vez). */
  tomar(productoId: number): BorradorPublicacion | null {
    const borrador = this.borradores.get(productoId) ?? null;
    this.borradores.delete(productoId);
    return borrador;
  }
}