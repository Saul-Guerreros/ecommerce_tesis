import { CommonModule } from '@angular/common';
import { Component, effect } from '@angular/core';
import { ActivatedRoute, RouterModule } from '@angular/router';
import { PerfilCliente } from '../../core/models/perfil-cliente.model';
import { PerfilClienteService } from '../../core/services/perfil-cliente.service';
import { AuthService } from '../auth/service/auth.service';
import { CambiarPasswordComponent } from './cambiar-password/cambiar-password.component';
import { DatosPersonalesComponent } from './datos-personales/datos-personales.component';
import { DireccionesComponent } from './direcciones/direcciones.component';
import { MisServiciosComponent } from './mis-servicios/mis-servicios.component';

@Component({
  selector: 'app-perfil-cliente',
  standalone: true,
  imports: [
    CommonModule,
    RouterModule,
    DatosPersonalesComponent,
    DireccionesComponent,
    CambiarPasswordComponent,
    MisServiciosComponent,   // <-- faltaba
  ],
  templateUrl: './perfil-cliente.component.html',
  styleUrl: './perfil-cliente.component.css',
})
export class PerfilClienteComponent {
  cargando = true;
  perfil: PerfilCliente | null = null;
  tabActiva: 'datos' | 'direcciones' | 'password' | 'servicios' = 'datos';
  
  constructor(
    private perfilClienteService: PerfilClienteService,
    private authService: AuthService,
	private route: ActivatedRoute
  ) {
  if (this.route.snapshot.queryParamMap.get('tab') === 'servicios') this.tabActiva = 'servicios';
    effect(() => {
      if (this.authService.sesion()) {
        this.cargarPerfil();
      }
    });
  }

  private cargarPerfil(): void {
    this.cargando = true;
    this.perfilClienteService.obtener().subscribe({
      next: (resp) => {
        this.perfil = resp.data;
        this.cargando = false;
      },
      error: () => (this.cargando = false),
    });
  }

  // El hijo (datos-personales) ya actualizó el backend; acá solo
  // refrescamos el estado local y sincronizamos el saludo del header.
  onPerfilActualizado(perfilActualizado: PerfilCliente): void {
    this.perfil = perfilActualizado;
    this.authService.actualizarUsuarioLocal({
      full_name: perfilActualizado.persona.nombre_completo,
    });
  }
}