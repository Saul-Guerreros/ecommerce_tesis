import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { AuthService } from '../service/auth.service';

@Component({
  selector: 'app-register',
  standalone: true,
  imports: [FormsModule, RouterModule],
  templateUrl: './register.component.html',
  styleUrl: './register.component.css',
})
export class RegisterComponent implements OnInit {
  // Campos alineados a AuthController::register() (persona + cliente + usuario)
  nombre1 = '';
  nombre2 = '';
  apellido1 = '';
  apellido2 = '';
  celular = '';
  email = '';
  password = '';
  password_confirmation = '';
  terminos = false;

  procesando = false;
  private readonly regexEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  constructor(
    private authService: AuthService,
    private router: Router,
    private toastr: ToastrService,
    private route: ActivatedRoute,
  ) {}

  /**
   * Invitación del correo de servicio técnico (/register?email=...): precarga el correo con el
   * que el cliente fue registrado en la sucursal, así la cuenta se vincula a su historial.
   */
  ngOnInit(): void {
    const email = this.route.snapshot.queryParamMap.get('email');
    if (email && this.regexEmail.test(email.trim())) {
      this.email = email.trim();
    }
  }

  register(): void {
    if (this.procesando) return;

    const error = this.validar();
    if (error) {
      this.toastr.warning(error, 'Revisá los datos');
      return;
    }

    this.procesando = true;
    const data = {
      nombre1: this.nombre1.trim(),
      nombre2: this.nombre2.trim() || null,
      apellido1: this.apellido1.trim(),
      apellido2: this.apellido2.trim() || null,
      celular: this.celular.trim(),
      email: this.email.trim(),
      password: this.password,
    };

    this.authService.register(data).subscribe({
      next: () => {
        this.procesando = false;
        this.toastr.success('Te enviamos un correo para activar tu cuenta.', 'Cuenta creada');
        this.router.navigateByUrl('/login');
      },
      error: (err: any) => {
        this.procesando = false;
        const mensaje = err?.error ? this.parseErrores(err.error) : null;
        this.toastr.error(mensaje || 'No se pudo completar el registro. Revisá los datos.', 'Error en el registro');
      },
    });
  }

  /** Devuelve el primer error encontrado, o null si todo es válido. */
  private validar(): string | null {
    if (!this.nombre1.trim() || !this.apellido1.trim()) return 'Ingresá tu primer nombre y tu primer apellido.';
    if (!this.celular.trim()) return 'Ingresá tu número de celular.';
    if (!this.regexEmail.test(this.email.trim())) return 'Ingresá un correo electrónico válido.';
    if (this.password.length < 8) return 'La contraseña debe tener al menos 8 caracteres.';
    if (this.password !== this.password_confirmation) return 'Las contraseñas no coinciden.';
    if (!this.terminos) return 'Debés aceptar los Términos y Condiciones y la Política de Privacidad.';
    return null;
  }

  /** Primer mensaje de error de validación de Laravel. */
  private parseErrores(errorBody: any): string | null {
    try {
      const errores = typeof errorBody === 'string' ? JSON.parse(errorBody) : errorBody;
      const primerCampo = Object.keys(errores)[0];
      if (primerCampo && Array.isArray(errores[primerCampo])) {
        return errores[primerCampo][0];
      }
    } catch {
      // se usa el mensaje genérico
    }
    return null;
  }
}