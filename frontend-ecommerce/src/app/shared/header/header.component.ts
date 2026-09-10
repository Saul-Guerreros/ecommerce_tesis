import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { AuthService } from '../../pages/auth/service/auth.service';

@Component({
  selector: 'app-header',
  standalone: true,
  imports: [RouterModule, CommonModule],
  templateUrl: './header.component.html',
  styleUrl: './header.component.css'
})
export class HeaderComponent {

  constructor(public authService: AuthService) {}

  // Leer el signal dentro del getter deja el template suscrito a él:
  // al cambiar la sesión, Angular refresca la vista por su cuenta.
  get isLoggedIn(): boolean {
    return this.authService.sesion();
  }

  get userName(): string {
    return this.authService.user?.full_name || this.authService.user?.email || '';
  }

  logout(): void {
    this.authService.logout();
  }
}