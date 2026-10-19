import { Component } from '@angular/core';
import { Router, RouterModule } from '@angular/router';
import { AuthService } from '../../auth/service/auth.service';

/** C1 - Landing pública del servicio técnico. */
@Component({
  selector: 'app-servicio-landing',
  standalone: true,
  imports: [RouterModule],
  templateUrl: './servicio-landing.component.html',
  styleUrl: './servicio-landing.component.css',
})
export class ServicioLandingComponent {
  readonly pasos = [
    { icono: 'file-edit', titulo: 'Solicitás', texto: 'Contanos qué le pasa a tu equipo y cómo nos lo hacés llegar.' },
    { icono: 'search', titulo: 'Diagnosticamos', texto: 'Un técnico lo revisa y te enviamos el presupuesto.' },
    { icono: 'check', titulo: 'Aprobás', texto: 'Decidís desde tu perfil si avanzamos con la reparación.' },
    { icono: 'happy', titulo: 'Lo retirás', texto: 'Te avisamos cuando esté listo, con garantía del trabajo.' },
  ];

  constructor(private auth: AuthService, private router: Router) {}

  solicitar(): void {
    // Sin sesión, el guard manda a login; acá solo se explica por qué.
    this.router.navigate([this.auth.isLoggedIn() ? '/servicio-tecnico/solicitar' : '/login']);
  }
}
