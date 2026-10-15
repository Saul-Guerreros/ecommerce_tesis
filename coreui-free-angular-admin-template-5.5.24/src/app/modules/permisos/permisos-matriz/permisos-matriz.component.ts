import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { PermisosService } from '../service/permisos.service';
import { CambioRol, GrupoPermisos, MatrizPermisos, OpcionPermiso, RolColumna } from '../models/permisos.model';

@Component({
  selector: 'app-permisos-matriz',
  standalone: true,
  imports: [CommonModule, FormsModule, CardModule, TableModule, ButtonModule, BadgeModule, SpinnerModule, FormModule],
  templateUrl: './permisos-matriz.component.html',
})
export class PermisosMatrizComponent implements OnInit {
  matriz: MatrizPermisos | null = null;
  cargando = false;
  guardando = false;
  busqueda = '';

  /** Estado actual de los checkboxes y copia de lo guardado (para detectar cambios). */
  private seleccion = new Map<number, Set<string>>();
  private original = new Map<number, Set<string>>();

  constructor(private service: PermisosService, private toastr: ToastrService) {}

  ngOnInit(): void {
    this.cargar();
  }

  cargar(): void {
    this.cargando = true;
    this.service.matriz().subscribe({
      next: (m) => {
        this.matriz = m;
        const todos = m.grupos.flatMap((g) => g.opciones.map((o) => o.permiso));

        this.seleccion.clear();
        this.original.clear();
        for (const rol of m.roles) {
          const base = rol.bloqueado ? todos : (m.asignados[String(rol.rol_id)] ?? []);
          this.seleccion.set(rol.rol_id, new Set(base));
          this.original.set(rol.rol_id, new Set(base));
        }
        this.cargando = false;
      },
      error: () => {
        this.toastr.error('No se pudo cargar la matriz de permisos', 'Error');
        this.cargando = false;
      },
    });
  }

  // --- Consulta de estado ---

  marcado(rolId: number, permiso: string): boolean {
    return this.seleccion.get(rolId)?.has(permiso) ?? false;
  }

  esEditable(rol: RolColumna, op: OpcionPermiso): boolean {
    return !rol.bloqueado && !op.fijo;
  }

  tieneEditables(g: GrupoPermisos): boolean {
    return g.opciones.some((o) => !o.fijo);
  }

  seccionCompleta(rol: RolColumna, g: GrupoPermisos): boolean {
    const editables = g.opciones.filter((o) => !o.fijo);
    return editables.length > 0 && editables.every((o) => this.marcado(rol.rol_id, o.permiso));
  }

  // --- Edición ---

  alternar(rol: RolColumna, op: OpcionPermiso, marcar: boolean): void {
    if (!this.esEditable(rol, op)) { return; }
    const set = this.seleccion.get(rol.rol_id)!;
    marcar ? set.add(op.permiso) : set.delete(op.permiso);
  }

  alternarSeccion(rol: RolColumna, g: GrupoPermisos, marcar: boolean): void {
    for (const op of g.opciones) {
      this.alternar(rol, op, marcar);
    }
  }

  // --- Cambios pendientes ---

  private diferencias(rolId: number): number {
    const actual = this.seleccion.get(rolId) ?? new Set<string>();
    const base = this.original.get(rolId) ?? new Set<string>();
    let n = 0;
    actual.forEach((p) => { if (!base.has(p)) { n++; } });
    base.forEach((p) => { if (!actual.has(p)) { n++; } });
    return n;
  }

  get cambiosPendientes(): number {
    if (!this.matriz) { return 0; }
    return this.matriz.roles
      .filter((r) => !r.bloqueado)
      .reduce((total, r) => total + this.diferencias(r.rol_id), 0);
  }

  descartar(): void {
    this.original.forEach((set, rolId) => this.seleccion.set(rolId, new Set(set)));
  }

  guardar(): void {
    if (!this.matriz || this.guardando) { return; }

    const cambios: CambioRol[] = this.matriz.roles
      .filter((r) => !r.bloqueado && this.diferencias(r.rol_id) > 0)
      .map((r) => ({ rol_id: r.rol_id, permisos: Array.from(this.seleccion.get(r.rol_id) ?? []) }));

    if (cambios.length === 0) { return; }

    this.guardando = true;
    this.service.guardar(cambios).subscribe({
      next: (resp) => {
        this.guardando = false;
        this.toastr.success(resp.message, 'Éxito');
        this.cargar();
      },
      error: (err) => {
        this.guardando = false;
        this.toastr.error(err?.error?.message ?? 'No se pudieron guardar los permisos', 'Error');
      },
    });
  }

  // --- Búsqueda ---

  limpiarFiltros(): void {
    this.busqueda = '';
  }

  opcionesVisibles(g: GrupoPermisos): OpcionPermiso[] {
    const q = this.busqueda.trim().toLowerCase();
    if (!q) { return g.opciones; }
    return g.opciones.filter((o) =>
      o.nombre.toLowerCase().includes(q) || o.urls.some((u) => u.toLowerCase().includes(q)));
  }

  gruposFiltrados(): GrupoPermisos[] {
    return (this.matriz?.grupos ?? []).filter((g) => this.opcionesVisibles(g).length > 0);
  }
}
