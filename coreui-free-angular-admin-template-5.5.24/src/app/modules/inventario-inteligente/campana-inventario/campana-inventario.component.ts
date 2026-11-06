// modules/inventario-inteligente/campana-inventario/campana-inventario.component.ts
import { Component, DestroyRef, computed, inject, signal } from '@angular/core';
import { DecimalPipe } from '@angular/common';
import { Router, RouterLink } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { switchMap, timer } from 'rxjs';
import { ToastrService } from 'ngx-toastr';
import { BadgeModule, DropdownModule } from '@coreui/angular';
import { IconDirective } from '@coreui/icons-angular';
import { AuthService } from '../../../auth/services/auth.service';
import { InventarioInteligenteService } from '../service/inventario-inteligente.service';
import { NotificacionesInventario, TIPOS_ALERTA } from '../models/inventario-inteligente.model';

/**
 * Campana del header con las alertas de reposición:
 *  - Badge con el total (rojo si hay agotados/críticos, amarillo si solo "reponer").
 *  - Desplegable con las 5 más urgentes y acceso al panel.
 *  - Aviso emergente una vez por sesión si hay agotados o críticos.
 * Se refresca al entrar, cada 5 minutos y después de cada acción sobre alertas.
 */
@Component({
  selector: 'app-campana-inventario',
  standalone: true,
  imports: [DecimalPipe, RouterLink, DropdownModule, BadgeModule, IconDirective],
  templateUrl: './campana-inventario.component.html',
})
export class CampanaInventarioComponent {
  private service = inject(InventarioInteligenteService);
  private auth = inject(AuthService);
  private toastr = inject(ToastrService);
  private router = inject(Router);
  private destroyRef = inject(DestroyRef);

  private static readonly REFRESCO_MS = 5 * 60 * 1000;

  readonly TIPOS = TIPOS_ALERTA;
  readonly visible = this.auth.tienePermiso('inventario-inteligente.ver');
  readonly notif = signal<NotificacionesInventario | null>(null);

  readonly total = computed(() => this.notif()?.total_reposicion ?? 0);
  readonly urgentes = computed(() => (this.notif()?.agotado ?? 0) + (this.notif()?.critico ?? 0));

  constructor() {
    if (!this.visible) return;

    this.service.notificaciones.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((n) => {
      this.notif.set(n);
       if (n) this.avisarSiHayUrgentesNuevas(n);
    });

    timer(0, CampanaInventarioComponent.REFRESCO_MS)
      .pipe(switchMap(() => this.service.getNotificaciones()), takeUntilDestroyed(this.destroyRef))
      .subscribe({ error: () => {} });
  }

  /** Toast al iniciar sesión: el administrador se entera aunque no abra el módulo. */
    /**
   * Muestra el aviso cuando aparece una alerta urgente NUEVA o que EMPEORÓ
   * (crítico -> agotado), sin necesidad de volver a iniciar sesión.
   * No se repite por las mismas alertas: se compara con lo último avisado.
   */
  private avisarSiHayUrgentesNuevas(n: NotificacionesInventario): void {
    const clave = `inventario-aviso:${this.auth.currentUser()?.email ?? ''}`;
    const actuales = n.urgentes
      .filter((a) => a.tipo === 'agotado' || a.tipo === 'critico')
      .map((a) => `${a.id}:${a.tipo}`);

    let anterior: { agotado: number; critico: number; claves: string[] } | null = null;
    try {
      anterior = JSON.parse(sessionStorage.getItem(clave) ?? 'null');
    } catch {
      anterior = null;
    }

    // Se guarda siempre el estado actual (si una alerta se resuelve, la próxima vez cuenta como nueva).
    sessionStorage.setItem(clave, JSON.stringify({ agotado: n.agotado, critico: n.critico, claves: actuales }));

    if (n.agotado + n.critico === 0) return;

    const hayNuevas = !anterior
      || n.agotado > anterior.agotado
      || n.critico > anterior.critico
      || actuales.some((c) => !anterior!.claves.includes(c));

    if (hayNuevas) this.mostrarAviso(n);
  }

  private mostrarAviso(n: NotificacionesInventario): void {
    const partes = [n.agotado ? `${n.agotado} agotado(s)` : '', n.critico ? `${n.critico} crítico(s)` : ''].filter(Boolean);

    this.toastr
      .warning(`${partes.join(' y ')}. Hacé clic para revisar.`, '⚠ Reposición de stock urgente', {
        timeOut: 15000, closeButton: true, progressBar: true,
      })
      .onTap.subscribe(() => this.router.navigate(['/inventario/inteligente']));
  }
}